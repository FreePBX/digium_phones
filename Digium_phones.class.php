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
	}
	public function uninstall() {
	}
	public function backup() {
	}
	public function restore($backup) {
	}
};
