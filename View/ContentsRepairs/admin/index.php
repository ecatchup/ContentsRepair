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
		<h3>利用方法</h3>
		<ul>
			<li>1. と 2. を順番に実行してください。
				<ul>
			<li>1 実行することで app/tmp/logs/log_contents_repair.log にチェック結果が記録されます。</li>
			<li>2 実行することでコンテンツ管理のデータに対して修復を試みます。
				<ul>
					<li><strong>2 の実行前に、必ずDBのバックアップを取得してください。</strong></li>
					<li>2 の実行には時間がかかる場合があります。実行中は他の操作を行わないでください。
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
						<strong>修復実行後、コンテンツの並び順は可能な限り以前のままとなる仕組みですが、順序の保証は不可能なため、コンテンツ一覧画面のスクショを撮っておくなどしてご確認ください。</strong>
					</li>
					<li>
						ツール実行後、ゴミ箱の中身を空にしてください。
					</li>
				</ul>
			</li>
		</ul>
	</div>
</section>

<section class="section">
	<h3>ツール</h3>
		<ul>
			<li>1.<?php // TreeBehavior::verifyする ?>
				<?php $this->BcBaser->link('コンテンツ管理のデータの整合性をチェックする',
					['action' => 'verify_contents_tree'],
					['class' => 'button exec-verify'],
				); ?>
			</li>
			<li>2.<?php // TreeBehavior::reorderする ?>
				<?php $this->BcBaser->link('コンテンツ管理のデータの整合性を修復する',
					['action' => 'reflesh_contents', '?' => ['mode' => 'addlft']],
					['class' => 'button exec-repair'],
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
			['class' => 'button-small exec-download'],
		); ?>
		<?php endif ?>
	</h2>

		<?php echo $this->BcForm->textarea('LogContentsRepair.log', [
			'value' => $repairLog,
			'style' => 'width:99%;height:300px;font-size:12px',
			'readonly' => 'readonly',
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
