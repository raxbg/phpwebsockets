<?php
interface iComponent {
	/* This function must check if the client is in the extension's clients list first and if not return false as soon as possible.
	 * If the client belongs to the extension, the data should be processed and the function must return true if everything is ok.
	 * Return false in any other case
	 */
	public function onMessage($client, $data);
}
