<?php
/*
 * SepaXmlCreator - by Thomas Schiffler.de
 * http://www.ThomasSchiffler.de/2013_09/code-schnipsel/sepa-sammeluberweisung-xml-datei-mit-php-erstellen
 *
 * Copyright (c) 2013 Thomas Schiffler (http://www.ThomasSchiffler.de
 * GPL (http://www.opensource.org/licenses/gpl-license.php) license.
 *
 * Namespace and translation by Korbinian Pauli
 */

namespace SepaXmlCreator;

class SepaTransaction {
	var $end2end, $iban, $bic, $recipient, $reference, $amount;

	private $mandateId, $mandateDate, $mandateChange;

	public function __construct() {
		$this->end2end = "NOTPROVIDED";
	}

	public function setEnd2End($end2end) {
		$this->end2end = $end2end;
	}

	public function setIban($iban) {
		$this->iban = str_replace(' ','',$iban);
	}

	public function setBic($bic) {
		$this->bic = $bic;
	}

	public function setRecipient($recipient) {
		$this->recipient = $recipient;
	}

	public function setReference($reference) {
		$this->reference = $reference;
	}

	public function setAmount($amount) {
		$this->amount = $amount;
	}


	/*
	 * Method for adding sepa mandates which are needed for SEPA direct debits. Id is mandatory. Date format: YYYY-MM-DD
	 *
	 * @param String $id
	 * @param String $mandateDate
	 * @param boolean $mandateChanged - true if mandate changed since issuance
	 */
	function setMandate($id, $mandateDate = null, $mandatChanged = true) {
		$this->mandateId = $id;
		$this->mandateChanged = $mandatChanged;

		if (!isset($mandateDate)) {
			$this->mandateDate = date('Y-m-d', time());
		} else {
			$this->mandateDate = $mandateDate;
		}
	}



	function normalizeString($input) {
		// Only below characters can be used within the XML tags according the guideline.
		// a b c d e f g h i j k l m n o p q r s t u v w x y z
		// A B C D E F G H I J K L M N O P Q R S T U V W X Y Z
		// 0 1 2 3 4 5 6 7 8 9
		// / - ? : ( ) . , ‘ +
		// Space
		//
		// Create a normalized array and cleanup the string $XMLText for unexpected characters in names
		$normalizeChars = array(
				'Á'=>'A', 'À'=>'A', 'Â'=>'A', 'Ã'=>'A', 'Å'=>'A', 'Ä'=>'Ae', 'Æ'=>'AE', 'Ç'=>'C',
				'É'=>'E', 'È'=>'E', 'Ê'=>'E', 'Ë'=>'E', 'Í'=>'I', 'Ì'=>'I', 'Î'=>'I', 'Ï'=>'I', 'Ð'=>'Eth',
				'Ñ'=>'N', 'Ó'=>'O', 'Ò'=>'O', 'Ô'=>'O', 'Õ'=>'O', 'Öe'=>'O', 'Ø'=>'O',
				'Ú'=>'U', 'Ù'=>'U', 'Û'=>'U', 'Ü'=>'Ue', 'Ý'=>'Y',

				'á'=>'a', 'à'=>'a', 'â'=>'a', 'ã'=>'a', 'å'=>'a', 'ä'=>'ae', 'æ'=>'ae', 'ç'=>'c',
				'é'=>'e', 'è'=>'e', 'ê'=>'e', 'ë'=>'e', 'í'=>'i', 'ì'=>'i', 'î'=>'i', 'ï'=>'i', 'ð'=>'eth',
				'ñ'=>'n', 'ó'=>'o', 'ò'=>'o', 'ô'=>'o', 'õ'=>'o', 'ö'=>'oe', 'ø'=>'o',
				'ú'=>'u', 'ù'=>'u', 'û'=>'u', 'ü'=>'ue', 'ý'=>'y',

				'ß'=>'ss', 'þ'=>'thorn', 'ÿ'=>'y',

				'&'=>'u.', '@'=>'at', '#'=>'h', '$'=>'s', '%'=>'perc', '^'=>'-','*'=>'-'
						);

		$output = strtr($input, $normalizeChars);

		return $output;
	}

}

class SepaXmlCreator {
	var $transactions = array();

	var $debitorName, $debitorIban, $debitorBic;
	var $offset = 0, $fixedDate;
	var $currency = "EUR";

	// Mode = 1 -> SEPA transfer / Mode = 2 -> SEPA debit
	var $mode = 1;
	var $isFirst = true;

	// creditor id
	var $creditorIdentifier;

	// XML-Errors
	private $xmlerrors;

	function setDebitorValues($name, $iban, $bic) {
		trigger_error('Use setAccountValues($name, $iban, $bic) instead', E_USER_DEPRECATED);

		$this->setAccountValues($name, $iban, $bic);
	}

	public function setAccountValues($name, $iban, $bic) {
		$this->accountName = $name;
		$this->accountIban = $iban;
		$this->accountBic = $bic;
	}

	function setCreditorIdentifier($creditorIdentifier) {
		$this->creditorIdentifier = $creditorIdentifier;
	}

	public function setCurrency($currency) {
		$this->currency = $currency;
	}

	public function addTransaction($transaction) {
		array_push($this->transactions, $transaction);
	}

	function setExecutionOffset($offset) {
		$this->offset = $offset;
	}

	function setExecutionDate($date) {
		$this->fixedDate = $date;
	}

	function generateSepaTransferXml() {
		// Set Mode = 1 -> SEPA transfer
		$this->mode = 1;
		return $this->getGeneratedXml();
	}

	function generateSepaDirectDebitXml() {
		// Set Mode = 2 -> SEPA Direct debit
		$this->mode = 2;

		return $this->getGeneratedXml();
	}

	function setIsFolgelastschrift() {
		$this->isFirst = false;
	}

	function getGeneratedXml() {
		$dom = new \DOMDocument('1.0', 'utf-8');

		// Build Document-Root
		$document = $dom->createElement('Document');
		if ($this->mode == 2) {
			$document->setAttribute('xmlns', 'urn:iso:std:iso:20022:tech:xsd:pain.008.002.02');
			$document->setAttribute('xsi:schemaLocation', 'urn:iso:std:iso:20022:tech:xsd:pain.008.002.02 pain.008.002.02.xsd');
		} else {
			$document->setAttribute('xmlns', 'urn:iso:std:iso:20022:tech:xsd:pain.001.002.03');
			$document->setAttribute('xsi:schemaLocation', 'urn:iso:std:iso:20022:tech:xsd:pain.001.002.03 pain.001.002.03.xsd');
		}
		$document->setAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
		$dom->appendChild($document);

		// Build Content-Root
		if ($this->mode == 2) {
			$content = $dom->createElement('CstmrDrctDbtInitn');
		} else {
			$content = $dom->createElement('CstmrCdtTrfInitn');
		}

		$document->appendChild($content);

		// Build Header
		$header = $dom->createElement('GrpHdr');
		$content->appendChild($header);

		$creationTime = time();

		// Msg-ID
		$header->appendChild($dom->createElement('MsgId', $this->debitorBic . '00' . date('YmdHis', $creationTime)));
		$header->appendChild($dom->createElement('CreDtTm', date('Y-m-d', $creationTime) . 'T' . date('H:i:s', $creationTime) . '.000Z'));
		$header->appendChild($dom->createElement('NbOfTxs', count($this->transactions)));
		$header->appendChild($initatorName = $dom->createElement('InitgPty'));
		$initatorName->appendChild($dom->createElement('Nm', $this->debitorName));

		// PaymentInfo
		$paymentInfo = $dom->createElement('PmtInf');
		$content->appendChild($paymentInfo);

		$paymentInfo->appendChild($dom->createElement('PmtInfId', 'PMT-ID0-' . date('YmdHis', $creationTime)));
		switch ($this->mode) {
			case 2:
				// 2 = SEPA debit
				$paymentInfo->appendChild($dom->createElement('PmtMtd', 'DD'));
				break;
			default:
				// Default / 1 = SEPA transfer
				$paymentInfo->appendChild($dom->createElement('PmtMtd', 'TRF'));
				break;
		}

		$paymentInfo->appendChild($dom->createElement('BtchBookg', 'true'));
		$paymentInfo->appendChild($dom->createElement('NbOfTxs', count($this->transactions)));
		$paymentInfo->appendChild($dom->createElement('CtrlSum', number_format( $this->getTotalAmount(), 2, '.', '')));
		$paymentInfo->appendChild($tmp1 = $dom->createElement('PmtTpInf'));
		$tmp1->appendChild($tmp2 = $dom->createElement('SvcLvl'));
		$tmp2->appendChild($dom->createElement('Cd', 'SEPA'));

		if ($this->mode == 2) {
			// additional attributes for debits
			$tmp1->appendChild($tmp2 = $dom->createElement('LclInstrm'));
			$tmp2->appendChild($dom->createElement('Cd', 'CORE'));
			if ($this->isFirst) {
				$tmp1->appendChild($dom->createElement('SeqTp', 'FRST'));
			} else {
				$tmp1->appendChild($dom->createElement('SeqTp', 'RCUR'));
			}
		}

		// calculation execution date
		if (isset($this->fixedDate)) {
			$executionDate = $this->fixedDate;
		} else {
			$executionTimestamp = $creationTime;
			if ($this->offset > 0) {
				$executionTimestamp = $executionTimestamp + (24 * 3600 * $this->offset);
			}

			$executionDate = date('Y-m-d', $executionTimestamp);
		}

		if ($this->mode == 2) {
			$paymentInfo->appendChild($dom->createElement('ReqdColltnDt', $executionDate));
		} else {
			$paymentInfo->appendChild($dom->createElement('ReqdExctnDt', $executionDate));
		}

		// add own account data
		if ($this->mode == 2) {
			$paymentInfo->appendChild($tmp1 = $dom->createElement('Cdtr'));
		} else {
			$paymentInfo->appendChild($tmp1 = $dom->createElement('Dbtr'));
		}
		$tmp1->appendChild($dom->createElement('Nm', $this->accountName));

		if ($this->mode == 2) {
			$paymentInfo->appendChild($tmp1 = $dom->createElement('CdtrAcct'));
		} else {
			$paymentInfo->appendChild($tmp1 = $dom->createElement('DbtrAcct'));
		}

		$tmp1->appendChild($tmp2 = $dom->createElement('Id'));
		$tmp2->appendChild($dom->createElement('IBAN', $this->accountIban));

		if ($this->mode == 2) {
			$paymentInfo->appendChild($tmp1 = $dom->createElement('CdtrAgt'));
		} else {
			$paymentInfo->appendChild($tmp1 = $dom->createElement('DbtrAgt'));
		}

		$tmp1->appendChild($tmp2 = $dom->createElement('FinInstnId'));
		$tmp2->appendChild($dom->createElement('BIC', $this->accountBic));

		$paymentInfo->appendChild($dom->createElement('ChrgBr', 'SLEV'));

		if ($this->mode == 2) {
			$paymentInfo->appendChild($tmp1 = $dom->createElement('CdtrSchmeId'));
			$tmp1->appendChild($tmp2 = $dom->createElement('Id'));
			$tmp2->appendChild($tmp3 = $dom->createElement('PrvtId'));
			$tmp3->appendChild($tmp4 = $dom->createElement('Othr'));
			$tmp4->appendChild($dom->createElement('Id', $this->creditorIdentifier));
			$tmp4->appendChild($tmp5 = $dom->createElement('SchmeNm'));
			$tmp5->appendChild($dom->createElement('Prtry', 'SEPA'));
		}



		// Add transactions
		foreach ($this->transactions as $transaction) {

			if ($this->mode == 2) {
				$paymentInfo->appendChild($transactionElement = $dom->createElement('DrctDbtTxInf'));
			} else {
				$paymentInfo->appendChild($transactionElement = $dom->createElement('CdtTrfTxInf'));
			}

			// End2End setzen
			if (isset($transaction->end2end)) {
				$transactionElement->appendChild($tmp1 = $dom->createElement('PmtId'));
				$tmp1->appendChild($dom->createElement('EndToEndId', $transaction->end2end));
			}

			// Amount
			if ($this->mode == 2) {
				$transactionElement->appendChild($tmp2 = $dom->createElement('InstdAmt', number_format($transaction->amount, 2, '.', '')));
				$tmp2->setAttribute('Ccy', $this->currency);
			} else {
				$transactionElement->appendChild($tmp1 = $dom->createElement('Amt'));
				$tmp1->appendChild($tmp2 = $dom->createElement('InstdAmt', number_format($transaction->amount, 2, '.', '')));
				$tmp2->setAttribute('Ccy', $this->currency);
			}


			// Institut
			if ($this->mode == 2) {
				$transactionElement->appendChild($tmp1 = $dom->createElement('DbtrAgt'));
			} else {
				$transactionElement->appendChild($tmp1 = $dom->createElement('CdtrAgt'));
			}
			$tmp1->appendChild($tmp2 = $dom->createElement('FinInstnId'));
			$tmp2->appendChild($dom->createElement('BIC', $transaction->bic));

			// recipient
			if ($this->mode == 2) {
				$transactionElement->appendChild($tmp1 = $dom->createElement('Dbtr'));
			} else {
				$transactionElement->appendChild($tmp1 = $dom->createElement('Cdtr'));
			}
			$tmp1->appendChild($dom->createElement('Nm', $transaction->recipient));

			// IBAN
			if ($this->mode == 2) {
				$transactionElement->appendChild($tmp1 = $dom->createElement('DbtrAcct'));
			} else {
				$transactionElement->appendChild($tmp1 = $dom->createElement('CdtrAcct'));
			}
			$tmp1->appendChild($tmp2 = $dom->createElement('Id'));
			$tmp2->appendChild($dom->createElement('IBAN', $transaction->iban));


			if ($this->mode == 2) {
				$transactionElement->appendChild($tmp1 = $dom->createElement('UltmtDbtr'));
				$tmp1->appendChild($dom->createElement('Nm', $transaction->recipient));
			}


			if (strlen($transaction->reference) > 0) {
				$transactionElement->appendChild($tmp1 = $dom->createElement('RmtInf'));
				$tmp1->appendChild($dom->createElement('Ustrd', $transaction->reference));
			}

		}

		// export XML
		return $dom->saveXML();
	}

	private function getTotalAmount() {
		$amount = 0;

		foreach ($this->transactions as $transaction) {
			$amount += $transaction->amount;
		}

		return $amount;
	}

	public function validateSepaDirectDebitXml($xmlfile) {
		return $this->validateXML($xmlfile, 'pain.008.002.02.xsd');
	}

	public function validateSepaTransferXml($xmlfile) {
		return $this->validateXML($xmlfile, 'pain.001.002.03.xsd');
	}

	protected function validateXML($xmlfile, $xsdfile) {
		libxml_use_internal_errors(true);

		$feed = new \DOMDocument();

		$result = $feed->load($xmlfile);
		if ($result === false) {
			$this->xmlerrors[] = "Document is not well formed\n";
		}
		if (@($feed->schemaValidate(dirname(__FILE__) . '/' . $xsdfile))) {

			return true;
		} else {
			$this->xmlerrors[] = "! Document is not valid:\n";
			$errors = libxml_get_errors();

			foreach ($errors as $error) {
				$this->xmlerrors[] = "---\n" . sprintf("file: %s, line: %s, column: %s, level: %s, code: %s\nError: %s",
						basename($error->file),
						$error->line,
						$error->column,
						$error->level,
						$error->code,
						$error->message
				);
			}
		}
		return false;
	}

	public function printXmlErrors() {

		if (!is_array($this->xmlerrors)) return;
		foreach ($this->xmlerrors as $error) {
			echo $error;

		}
	}

}