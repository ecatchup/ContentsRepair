<?php
/**
 * [ModelEventListener] ContentsRepairModelEventListener
 */
class ContentsRepairModelEventListener extends BcModelEventListener {
	public $events = [
		'Content.beforeDelete',
	];

	public function __construct() {
		parent::__construct();
	}

	/**
	 * contentBeforeDelete
	 *
	 * @param CakeEvent $event
	 */
	public function contentBeforeDelete(CakeEvent $event) {
		$Model = $event->subject();
		$Model->Behaviors->unload('SoftDelete');
	}

}
