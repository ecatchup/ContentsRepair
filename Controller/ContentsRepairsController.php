<?php
/**
 * [Controller] ContentsRepair
 */
class ContentsRepairsController extends AppController {
	public $uses = [];
	public $components = ['Cookie', 'BcAuth', 'BcAuthConfigure'];

	public function beforeFilter() {
		parent::beforeFilter();

		if (!BcUtil::isAdminUser()) {
			$message = 'システム管理者以外はアクセスできません。';
			if ($this->Components->loaded('BcMessage')) {
				$this->BcMessage->setInfo($message);
			} else {
				$this->setMessage($message, true);
			}
			$this->redirect(['admin' => true, 'plugin' => null, 'controller' => 'dashboard', 'action' => 'index']);
		}
	}

	/**
	 * [ADMIN] 機能一覧
	 */
	public function admin_index() {
		$this->pageTitle = 'コンテンツデータ修復管理';
	}

	/**
	 * コンテンツ管理のツリー構造のチェックを行う
	 */
	public function admin_verity_contents_tree() {
		$this->_checkReferer();
		$Content = ClassRegistry::init('Content');
		$Content->Behaviors->unload('SoftDelete');
		$result = $Content->verify();
		if ($result === true) {
			$message = 'コンテンツのツリー構造に問題はありません。';
			if ($this->Components->loaded('BcMessage')) {
				$this->BcMessage->setSuccess($message, false);
			} else {
				$this->setMessage($message);
			}
			CakeLog::write(LOG_CONTENTS_REPAIR, '[Controller] '. $message);
		} else {
			CakeLog::write(LOG_CONTENTS_REPAIR, print_r($result, true));
			$message = 'コンテンツのツリー構造に問題があります。ログを確認してください。';
			if ($this->Components->loaded('BcMessage')) {
				$this->BcMessage->setError($message);
			} else {
				$this->setMessage($message, true, false);
			}
			CakeLog::write(LOG_CONTENTS_REPAIR, '[Controller] '. $message);
		}
		$this->redirect(['action' => 'index']);
	}

	/**
	 * [ADMIN] コンテンツ管理のデータの整合性を取り修復を試みる
	 * - 修復実行後、コンテンツ管理のツリー構造のチェックを行う
	 * @link https://book.cakephp.org/2.0/ja/core-libraries/behaviors/tree.html#id8
	 */
	public function admin_reflesh_contents() {
		$this->_checkReferer();
		CakeLog::write(LOG_CONTENTS_REPAIR, '[Controller] reflesh_contents 処理開始 ━━━━━━━━━━━━━━━━');

		clearAllCache();
		ini_set("max_execution_time", 0);
		set_time_limit(0);

		# 開始：処理時間記録
		$startTime = microtime(true);
		$timeMessage = "開始時間： ". date('Y-m-d H:i:s',(int)$startTime);
		CakeLog::write(LOG_CONTENTS_REPAIR, '[Controller] ' . $timeMessage);


		$Content = ClassRegistry::init('Content');
		$Content->Behaviors->unload('SoftDelete');

		try {
			// /admin/contents_repair/contents_repairs/reflesh_contents?mode=addlft
			if ( Hash::get($this->request->query, 'mode') && Hash::get($this->request->query, 'mode') === 'addlft' ) {
				CakeLog::write(LOG_CONTENTS_REPAIR, '[Controller] Content.lft_old を追加');
				// ALTER TABLE "contents" ADD COLUMN "lft_old" integer(8) DEFAULT 'NULL';
				$db = ConnectionManager::getDataSource($Content->useDbConfig);
				$options = [
					'field' => 'lft_old',
					'table' => $Content->table,
					'column' => ['type' => 'integer'],
					'default' => null,
					'null' => true,
				];
				$ret = $db->addColumn($options);
				if ($ret === false) {
					$message = 'Contentモデルの処理中にエラーが発生したため中止します。Content.lft_old の追加に失敗しました。テーブル名' . $Content->table . 'にカラム名 lft_old が存在する場合は削除してください。';
					if ($this->Components->loaded('BcMessage')) {
						$this->BcMessage->setError($message);
					} else {
						$this->setMessage($message, true, false);
					}
					$this->redirect(['action' => 'index']);
				} else {
					// UPDATE "contents" SET lft_old=lft;
					$Content->updateAll(['Content.lft_old' => 'Content.lft']);
					CakeLog::write(LOG_CONTENTS_REPAIR, '[Controller] Content.lft_old に Content.lft を複製');
				}
				clearAllCache();
			}
		} catch (\Throwable $th) {
			CakeLog::write(LOG_CONTENTS_REPAIR, print_r($th, true));
			$message = 'Contentモデルの処理中にエラーが発生したため中止します。';
			if ($this->Components->loaded('BcMessage')) {
				$this->BcMessage->setError($message);
			} else {
				$this->setMessage($message, true, false);
			}
			$this->redirect(['action' => 'index']);
		}

		// ■ lft のフィールドを複製して持たせる
		// ■ 全体に対して recover かける
		// parent_id を元に全ての左右のフィールドを再構築する
		$Content->recover('parent');

		// // ■ site_id = 0 のフォルダで level = 1 のものに対して、旧lft を基準として reorder かける
		$mainSiteList = $Content->find('all', [
			'conditions' => [
				'Content.site_id' => 0,
				'Content.type' => 'ContentFolder',
				'Content.level' => 1,
			],
			'order' => 'Content.lft_old ASC',
			'callbacks' => false,
			'recursive' => -1,
		]);
		foreach ($mainSiteList as $mainFolder) {
			CakeLog::write(LOG_CONTENTS_REPAIR, '[Controller] reorder実行したフォルダID: ' . $mainFolder['Content']['id']);
			$Content->reorder([
				'id' => $mainFolder['Content']['id'],
				'field' => 'lft_old',
				'verify' => false,
			]);
		}

		// サブサイト対応処理
		$SiteModel = ClassRegistry::init('Site');
		$subSiteList = $SiteModel->find('list', [
			'conditions' => [
				'Site.status' => true,
			],
			'recursive' => -1,
		]);
		if ($subSiteList) {
			CakeLog::write(LOG_CONTENTS_REPAIR, '[Controller] subsite処理開始');
			foreach ($subSiteList as $subSiteId => $subSite) {
				// ■ site_id = 2 のフォルダで level = 2 のものに対して、旧lft を基準として reorder かける
				$subSiteFolderList = $Content->find('all', [
					'conditions' => [
						'Content.site_id' => $subSiteId,
						'Content.type' => 'ContentFolder',
						'Content.level' => 2,
					],
					'order' => 'Content.lft_old ASC',
					'callbacks' => false,
					'recursive' => -1,
				]);
				foreach ($subSiteFolderList as $key => $subFolder) {
					CakeLog::write(LOG_CONTENTS_REPAIR, '[Controller] subsite'. $subSiteId .': reorder実行したフォルダID: ' . $subFolder['Content']['id']);
					$Content->reorder([
						'id' => $subFolder['Content']['id'],
						'field' => 'lft_old',
						'verify' => false,
					]);
				}
			}
			CakeLog::write(LOG_CONTENTS_REPAIR, '[Controller] subsite処理終了');
		}

		CakeLog::write(LOG_CONTENTS_REPAIR, '[Controller] Content.lft_old を削除');
		$db = ConnectionManager::getDataSource($Content->useDbConfig);
		$options = ['field' => 'lft_old', 'table' => $Content->table];
		$ret = $db->dropColumn($options);
		if ($ret === false) {
			CakeLog::write(LOG_CONTENTS_REPAIR, 'Content.lft_old の削除に失敗しました。※手動で削除してください。');
		}

		CakeLog::write(LOG_CONTENTS_REPAIR, '[Controller] reflesh_contents実行後');
		$result = $Content->verify();
		if($result === true) {
			$message = 'コンテンツのツリー構造に問題はありません。';
			if ($this->Components->loaded('BcMessage')) {
				$this->BcMessage->setSuccess($message, false);
			} else {
				$this->setMessage($message);
			}
			CakeLog::write(LOG_CONTENTS_REPAIR, '[Controller] '. $message);
		} else {
			CakeLog::write(LOG_CONTENTS_REPAIR, print_r($result, true));
			$message = 'コンテンツのツリー構造に問題があります。ログを確認してください。';
			if ($this->Components->loaded('BcMessage')) {
				$this->BcMessage->setError($message);
			} else {
				$this->setMessage($message, true, false);
			}
			CakeLog::write(LOG_CONTENTS_REPAIR, '[Controller] '. $message);
		}


		# 終了：処理時間記録
		$endTime = microtime(true);
		$timeMessage = "終了時間： ". date('Y-m-d H:i:s',(int)$endTime);
		CakeLog::write(LOG_CONTENTS_REPAIR, '[Controller] ' . $timeMessage);

		$syoriZikan = $endTime - $startTime;
		$timeMessage = "処理時間：". sprintf('%0.5f',$syoriZikan) ."秒";
		$timeMessage .= '：' . floor(($syoriZikan / 60)) . '分';
		CakeLog::write(LOG_CONTENTS_REPAIR, '[Controller] ' . $timeMessage);

		CakeLog::write(LOG_CONTENTS_REPAIR, '[Controller] reflesh_contents 処理終了 ━━━━━━━━━━━━━━━━');
		$this->redirect(['action' => 'index']);
	}

}
