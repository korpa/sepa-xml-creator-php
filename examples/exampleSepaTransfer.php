<?php
	// create new instance
	$creator = new \SepaXmlCreator\SepaXmlCreator();

	$creator->setAccountValues('name of my bank account', 'IBAN of my bank account', 'BIC of my bank account');


	/*
	Optional parameter. If not set, execution will be done as soon as possible
	1 for tomorrow, 2 for day after tomorrow and so on
	 */
	$creator->setExecutionOffset(3);

	// Create new transfer
	$transaction = new \SepaXmlCreator\SepaTransaction();
	// Amount
	$transaction->setAmount(10);
	// end2end reference (OPTIONAL)
	$transaction->setEnd2End('ID-00002');
	// recipient BIC
	$transaction->setBic('EMPFAENGERBIC');
	// recipient name
	$transaction->setRecipient('Mustermann, Max');
	// recipient IBAN
	$transaction->setIban('DE1234566..');
	// reference (OPTIONAL)
	$transaction->setReference('Test Buchung');
	// add transaction
	$creator->addTransaction($transaction);

	// repeat for as many transactions you like
	$transaction = new \SepaXmlCreator\SepaTransaction();
	$transaction->setAmount(7);
	$transaction->setBic('EMPFAENGERBIC');
	$transaction->setRecipient('Mustermann, Max');
	$transaction->setIban('DE1234566..');
	$creator->addTransaction($transaction);

	// generate the transfer file
	$sepaxml = $creator->generateSepaTransferXml();
	echo $sepaxml;

	file_put_contents('sepaTransfer-example.xml', $sepaxml);
	$creator->validateSepaTransferXml('sepaTransfer-example.xml');
	$creator->printXmlErrors();