<?php
require 'functions.php';

switch($_GET['action'])
{
	case 'person_add':
		responce(person_add($_GET));
	break;

	case 'person_list':
		if($res = person_list())
		{
			responce(true, $res);
		}
		
		responce(false);
	break;

	case 'ch_add':
		responce(ch_add($_GET['hash'], $_GET['name']));
	break;

	case 'interaction_add':
		responce(interaction_add($_GET['hash'], $_GET['name']));
	break;

	case 'for_person_gen':
		if($res = gen($_GET['hash']))
		{
			make_download($res);
		}

		header('Location: /');
	break;

	default:
		// 
	break;
}