<?php
/**
 * [ADMIN] ContentsRepair
 *
 * @copyright Copyright (c) Catchup, Inc.
 * @license MIT LICENSE
 */
?>
<section class="section">
	<div class="readme">
		<h2>利用方法</h2>
		<p style="margin-bottom: 3em;">「ツール」の各項目 1 と 2 と 3 を順番に実行してください。</p>

		<h3>1. コンテンツ管理のデータの整合性をチェックする</h3>
		<p style="margin-bottom: 2em;">実行することで app/tmp/logs/log_contents_repair.log にチェック結果が記録されます。</p>

		<h3>2. コンテンツ管理のツリー構造を修復する</h3>
		<p>実行することで、コンテンツ管理のデータに対して修復を試みます。</p>

		<ul style="margin-bottom: 2em;">
			<li><strong>実行前に、必ずDBのバックアップを取得してください。</strong></li>
			<li>実行には時間がかかる場合があります。実行中は他の操作を行わないでください。
				<ul>
					<li>
						実行環境により、2 の実行中にタイムアウトエラーが出る場合があります。
						その場合、ログファイルに「終了時間」の記録があることを確認してください。
						<br>
						終了時間が記録されている場合は、処理は正常に終了しています。<br>
						終了時間が記録されていない場合は、処理が途中で止まっている可能性があるため、DBバックアップから復旧してください。
					</li>
				</ul>
			</li>
			<li>
				<strong>修復実行後、コンテンツの並び順は可能な限り以前のままとなる仕組みですが、順序の保証は不可能なため、コンテンツ一覧画面のスクリーンショットを撮っておくなどしてご確認ください。</strong>
			</li>

			<?php if ($dbType === 'sqlite'): ?>
			<li>
				ツール実行後、ゴミ箱の中身を空にしてください。
			</li>
			<?php endif; ?>
		</ul>

		<h3>3. 「ユーティリティ」へ移動し「固定ページテンプレート書出」を実行する</h3>
		<p>
			ユーティリティへ移動したのち、「固定ページテンプレート書出」を実行してください。
		</p>
	</div>
</section>

<section class="section">
	<h3>ツール</h3>
		<ul>
			<li>1.<?php // TreeBehavior::verifyする ?>
				<?php $this->BcBaser->link('コンテンツ管理のデータの整合性をチェックする',
					['action' => 'verify_contents_tree'],
					['class' => 'button exec-verify']
				); ?>
			</li>
			<li>2.<?php // TreeBehavior::reorderする ?>
				<?php $this->BcBaser->link('コンテンツ管理のデータの整合性を修復する',
					['action' => 'reflesh_contents', '?' => ['mode' => 'addlft']],
					['class' => 'button exec-repair']
				); ?>
			</li>
			<li>3.<?php // PagesController::write_page_files() ?>
				<?php $this->BcBaser->link('ユーティリティへ移動し「固定ページテンプレート書出」を実行する',
					['controller' => 'tools', 'action' => 'index', 'plugin' => null],
					['class' => 'button']
				); ?>
			</li>
		</ul>
</section>

<div class="section">
	<h2>
		ログ: log_contents_repair.log
		<?php if ($availableZip): ?>
		&nbsp;&nbsp;&nbsp;&nbsp;
		<?php $this->BcBaser->link('ダウンロード',
			['action' => 'download'],
			['class' => 'button-small exec-download']
		); ?>
		<?php endif ?>
	</h2>

		<?php echo $this->BcForm->textarea('LogContentsRepair.log', [
			'value' => $repairLog,
			'style' => 'width:99%;height:300px;font-size:12px;',
			'readonly' => 'readonly'
		]); ?>
</div>

<script>
$(function () {
	$('#LogContentsRepairLog').scrollTop($('#LogContentsRepairLog')[0].scrollHeight);

	$('a.exec-verify').on('click', function () {
		if (confirm("コンテンツ管理データの整合性をチェックします。良いですか？")) {
			$("#Waiting").show();
		} else {
			return false;
		}
	});

	$('a.exec-repair').on('click', function () {
		if (confirm("DBのバックアップは取得しましたか？コンテンツ管理データの修復を試みます。良いですか？")) {
			$("#Waiting").show();
		} else {
			return false;
		}
	});
});
</script>
