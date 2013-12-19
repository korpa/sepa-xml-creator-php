<?php

	// include SepaXmlCreator class
	require_once '../src/SepaXmlCreator.class.php';



	// create new instance
	$creator = new \SepaXmlCreator\SepaXmlCreator();

	$creator->setAccountValues('name of my bank account', 'IBAN of my bank account', 'BIC of my bank account');


	//Add creditor identifier you get from
	$creator->setCreditorIdentifier("DE98ZZZ09999999999");


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
	// add mandate
	$transaction->setMandate("MANDATE0001", "2013-05-20", false);
	// add transaction
	$creator->addTransaction($transaction);

	// repeat for as many transactions you like
	$transaction = new \SepaXmlCreator\SepaTransaction();
	$transaction->setAmount(7);
	$transaction->setBic('EMPFAENGERBIC');
	$transaction->setRecipient('Mustermann, Max');
	$transaction->setIban('DE1234566..');
	$transaction->setMandate("MANDATE0002");
	$creator->addTransaction($transaction);

	// generate the transfer file
	$sepaxml = $creator->generateSepaDirectDebitXml();
	echo $sepaxml;

	file_put_contents('sepaDirectDebit-example.xml', $sepaxml);
	$creator->validateSepaDirectDebitXml('sepaDirectDebit-example.xml');
	$creator->printXmlErrors();