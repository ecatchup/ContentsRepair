<?php
/**
 * [ModelEventListener] ContentsRepairModelEventListener
 *
 * @copyright Copyright (c) Catchup, Inc.
 * @license MIT LICENSE
 */
class ContentsRepairModelEventListener extends BcModelEventListener {
	public $events = [
		'Content.beforeDelete',
	];

	public function contentBeforeDelete(CakeEvent $event) {
		$Model = $event->subject();
		$Model->Behaviors->unload('SoftDelete');
	}

}
