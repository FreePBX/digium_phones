<?php

namespace FreePBX\modules;

class Digium_phones implements \BMO {

	public function __construct($freepbx) {
		$this->FreePBX = $freepbx;
		$this->PJSip = $this->FreePBX->Core->getDriver('pjsip');
	}

	public function myDialplanHooks() {
		return true;
	}

	public function doDialplanHook(&$ext, $engine, $priority) {
		if (!empty($this->PJSip)) {
			$this->PJSip->addEndpoint('dpma_endpoint', 'context', 'dpma-invalid');
			$this->PJSip->addGlobal('default_outbound_endpoint', 'dpma_endpoint');
		}
	}

	public function doConfigPageInit($page) {
	}

	public function install() {
		// every hour
		$time = "5 * * * *";
		$this->FreePBX->Job->addClass('digium_phones', 'clearcdr', 'FreePBX\modules\Digium_phones\Job', $time);
        $this->FreePBX->Job->setEnabled('timeconditions', 'clearcdr', 'Yes');
	}
	public function uninstall() {
	}
	public function backup() {
	}
	public function restore($backup) {
	}
	public function cleanupcdr(){
		$cdrdbh =  $this->FreePBX->Cdr->getCdrDbHandle();
		$sth = $cdrdbh->prepare("DELETE from cdr where `dst`='digium_phone_module'");
        $sth->execute();
	}
};
