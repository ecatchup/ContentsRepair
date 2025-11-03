<?php
/**
 * [Controller] ContentsRepair
 *
 * @copyright Copyright (c) Catchup, Inc.
 * @license MIT LICENSE
 */
class ContentsRepairsController extends AppController {
	public $uses = [];
	public $components = ['Cookie', 'BcAuth', 'BcAuthConfigure'];

	public function beforeFilter() {
		parent::beforeFilter();

		if (!defined('LOG_CONTENTS_REPAIR')) {
			define('LOG_CONTENTS_REPAIR', 'log_contents_repair');
			CakeLog::config('log_contents_repair', [
				'engine' => 'FileLog',
				'types' => ['log_contents_repair'],
				'file' => 'log_contents_repair',
				'size' => '3MB',
				'rotate' => 5,
			]);
		}

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

		$repairLog = TMP .'logs'. DS .'log_contents_repair.log';
		if (file_exists($repairLog)) {
			$File = new File($repairLog);
			$repairLog = $File->read();
		}
		$this->set('repairLog', $repairLog);
		$this->set('availableZip', extension_loaded('zip'));

		// db になにを利用しているか判定する
		$dbType = $this->getDbType();
		$this->set('dbType', $dbType);
	}

	/**
	 * db になにを利用しているか判定する
	 * @return string
	 */
	private function getDbType() {
		$db = ConnectionManager::getDataSource('default');
		$datasource = strtolower(preg_replace('/^Database\/Bc/', '', $db->config['datasource']));
		return $datasource;
	}

	/**
	 * コンテンツ管理のツリー構造のチェックを行う
	 */
	public function admin_verify_contents_tree() {
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
			CakeLog::write(LOG_CONTENTS_REPAIR, '[Controller] verify_contents_tree '. $message);
		} else {
			CakeLog::write(LOG_CONTENTS_REPAIR, print_r($result, true));
			$message = 'コンテンツのツリー構造に問題があります。ログを確認してください。';
			if ($this->Components->loaded('BcMessage')) {
				$this->BcMessage->setError($message);
			} else {
				$this->setMessage($message, true, false);
			}
			CakeLog::write(LOG_CONTENTS_REPAIR, '[Controller] verify_contents_tree '. $message);
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

		// 既存の parent_id を元に全ての左右のフィールドを再構築する
		$Content->recover('parent');
		// ■ site_id = 0 のフォルダで level = 1 のものに対して、旧lft を基準として reorder かける
		// ツリー構造のデータ中のノード (と子ノード) を、パラメータで定義されたフィールドと指示によって、もう一度並び替える。このメソッドは、全てのノードの親を変更しません。
		// - 同一のサイトIDに絞ることで、サブサイトのツリー構造に影響を与えないようにする
		// - level = 1 は、サイト直下のフォルダ
		// - 並び替える基準値として lft_old を用いることで、可能な限り以前の並び順を保つ
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

		// キャッシュクリア（URL修正前）
		clearAllCache();

		// 各コンテンツの壊れている url 値を、本来あるべきURLに修正する
		$this->_repairContentUrls($Content, $subSiteList);

		// キャッシュクリア（URL修正後）
		clearAllCache();

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

	/**
	 * ログ・ファイルのダウンロード
	 */
	public function admin_download() {
		$this->_checkReferer();

		$zipEnable = extension_loaded('zip');
		if (!$zipEnable) {
			$message = 'ZIPモジュールがインストールされていないため、ログ・ファイルのダウンロードはできません。';
			if ($this->Components->loaded('BcMessage')) {
				$this->BcMessage->setError($message, false);
			} else {
				$this->setMessage($message, true);
			}
			$this->redirect(['action' => 'index']);
		}

		$logPath = TMP .'logs'. DS .'log_contents_repair.log';
		// $fileSize = 0;
		// $fileSize = filesize($logPath);
		if (!file_exists($logPath)) {
			$message = 'ログ・ファイルが存在しません。';
			if ($this->Components->loaded('BcMessage')) {
				$this->BcMessage->setInfo($message, false);
			} else {
				$this->setMessage($message, true);
			}
			$this->redirect(['action' => 'index']);
		}

		Configure::write('debug', 0);
		$this->autoRender = false;
		$this->response->disableCache();

		App::uses('BcZip', 'Lib');
		$bcZip = new BcZip();
		$tempZipFile = TMP .'logs'. DS . date('YmdHis') .'_log_contents_repair.zip';
		if ($bcZip->Zip->open($tempZipFile, ZipArchive::CREATE) === TRUE) {
			$bcZip->Zip->addFile($logPath, 'log_contents_repair.log');
			$bcZip->Zip->close();

			header("Content-Type: application/zip");
			header("Content-Disposition: attachment; filename=" . basename($tempZipFile) . ";");
			header("Content-Length: " . filesize($tempZipFile));
			while (ob_get_level()) { ob_end_clean(); }
			echo readfile($tempZipFile);
			unlink($tempZipFile);

		} else {
			$message = 'zipファイルの作成に失敗しました。ftpクライアントで直接ダウンロードしてください。';
			if ($this->Components->loaded('BcMessage')) {
				$this->BcMessage->setError($message, false);
			} else {
				$this->setMessage($message, true);
			}
			$this->redirect(['action' => 'index']);
		}
	}

	/**
	 * コンテンツURLを修復する
	 *
	 * @param Model $Content Contentモデル
	 * @param array $subSiteList サブサイトリスト
	 * @return array 修復結果 ['repaired' => 件数, 'unchanged' => 件数]
	 */
	private function _repairContentUrls($Content, $subSiteList = []) {
		CakeLog::write(LOG_CONTENTS_REPAIR, '[Controller] URL修正処理開始');

		$totalRepairedCount = 0;
		$totalUnchangedCount = 0;

		// メインサイト（site_id = 0）のURL修正
		CakeLog::write(LOG_CONTENTS_REPAIR, '[Controller] メインサイト(site_id=0)のURL修正開始');
		$mainContents = $Content->find('all', [
			'conditions' => [
				'Content.site_id' => 0,
				'NOT' => [
					'Content.site_root' => 1,
					'Content.deleted' => 1,
				],
			],
			'recursive' => -1,
			'order' => 'Content.lft ASC',
			'callbacks' => false,
		]);

		foreach ($mainContents as $content) {
			$result = $this->_repairSingleContentUrl($Content, $content);
			$totalRepairedCount += $result['repaired'];
			$totalUnchangedCount += $result['unchanged'];
		}
		CakeLog::write(LOG_CONTENTS_REPAIR, '[Controller] メインサイト(site_id=0)のURL修正完了');

		// サブサイト対応処理
		if ($subSiteList) {
			CakeLog::write(LOG_CONTENTS_REPAIR, '[Controller] サブサイトのURL修正開始');
			foreach ($subSiteList as $subSiteId => $subSite) {
				CakeLog::write(LOG_CONTENTS_REPAIR, "[Controller] サブサイト(site_id={$subSiteId})のURL修正開始");

				$subContents = $Content->find('all', [
					'conditions' => [
						'Content.site_id' => $subSiteId,
						'NOT' => [
							'Content.site_root' => 1,
							'Content.deleted' => 1,
						],
					],
					'recursive' => -1,
					'order' => 'Content.lft ASC',
					'callbacks' => false,
				]);

				foreach ($subContents as $content) {
					$result = $this->_repairSingleContentUrl($Content, $content);
					$totalRepairedCount += $result['repaired'];
					$totalUnchangedCount += $result['unchanged'];
				}

				CakeLog::write(LOG_CONTENTS_REPAIR, "[Controller] サブサイト(site_id={$subSiteId})のURL修正完了");
			}
			CakeLog::write(LOG_CONTENTS_REPAIR, '[Controller] サブサイトのURL修正完了');
		}

		CakeLog::write(LOG_CONTENTS_REPAIR, "[Controller] URL修正処理完了 - 修正件数: {$totalRepairedCount}件、変更なし: {$totalUnchangedCount}件");

		return [
			'repaired' => $totalRepairedCount,
			'unchanged' => $totalUnchangedCount
		];
	}

	/**
	 * 単一コンテンツのURLを修復する
	 *
	 * @param Model $Content Contentモデル
	 * @param array $content コンテンツデータ
	 * @return array 修復結果 ['repaired' => 0 or 1, 'unchanged' => 0 or 1]
	 */
	private function _repairSingleContentUrl($Content, $content) {
		$id = $content['Content']['id'];
		$currentUrl = $content['Content']['url'];

		// Content::createUrl() を使用して正しいURLを生成
		$correctUrl = $Content->createUrl($id, $content['Content']['plugin'], $content['Content']['type']);

		if ($correctUrl === false) {
			CakeLog::write(LOG_CONTENTS_REPAIR, "[URL修正] コンテンツID:{$id} - URL生成失敗");
			return ['repaired' => 0, 'unchanged' => 0];
		}

		// 現在のURLと正しいURLを比較
		if ($currentUrl !== $correctUrl) {
			// URL修正前後をログに記録（2行に分けて見やすく）
			CakeLog::write(LOG_CONTENTS_REPAIR, "[URL修正] コンテンツID:{$id} - 修正前: {$currentUrl}");
			CakeLog::write(LOG_CONTENTS_REPAIR, "[URL修正] コンテンツID:{$id} - 修正後: {$correctUrl}");

			// updatingRelated と updatingSystemData を一時的に無効化
			$updatingRelated = $Content->updatingRelated;
			$updatingSystemData = $Content->updatingSystemData;
			$Content->updatingRelated = false;
			$Content->updatingSystemData = false;

			// URLフィールドのみを更新（他のフィールドは変更しない）
			$Content->id = $id;
			if ($Content->saveField('url', $correctUrl, false)) {
				$Content->updatingRelated = $updatingRelated;
				$Content->updatingSystemData = $updatingSystemData;
				return ['repaired' => 1, 'unchanged' => 0];
			} else {
				CakeLog::write(LOG_CONTENTS_REPAIR, "[URL修正] コンテンツID:{$id} - 保存失敗");
				$Content->updatingRelated = $updatingRelated;
				$Content->updatingSystemData = $updatingSystemData;
				return ['repaired' => 0, 'unchanged' => 0];
			}
		} else {
			return ['repaired' => 0, 'unchanged' => 1];
		}
	}

}
