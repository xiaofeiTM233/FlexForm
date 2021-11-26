<?php
# @Author: Sen-Sai <Charlot>
# @Date:   15-05-2018 -- 10:46:23
# @Last modified by:   Charlot
# @Last modified time: 27-06-2018 -- 13:04:18
# @License: Mine
# @Copyright: 2018



/*
 *    What : WSForm api tasks
 *  Author : Sen-Sai
 *    Date : October 2017
 */
//use \MediawikiApi\Api\ApiUser;
$cookieParams = session_get_cookie_params();
$cookieParams['samesite'] = "Lax";
session_set_cookie_params($cookieParams);
session_start();
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;
//setcookie("wsform[type]", "danger", 0, '/');
//setcookie("wsform[txt]", "test", 0, '/');

$currentHost = $_SERVER['HTTP_HOST'];
$referrerHost = parse_url($_SERVER['HTTP_REFERER']);
/*
if ( strcmp($currentHost, $referrerHost['host']) !== 0)
{
    http_response_code(404);
    //include('myCustom404.php'); // provide your own 404 error page
    die('no no no sir'); // remove this if you want to execute the rest of the code inside the file before redirecting.
}
*/

//ERROR_REPORTING(E_ALL);
//ini_set('display_errors', 1);

require_once( 'WSForm.api.class.php' );
require_once( 'classes/recaptcha.class.php' );
require_once( 'modules/htmlpurifier/library/HTMLPurifier.auto.php' );

$i18n = new wsi18n();
$ret = false;

$removeList = array();

$imageHandler = array();

$imageHandler  = [
    IMAGETYPE_JPEG => [
        'load' => 'imagecreatefromjpeg',
        'save' => 'imagejpeg',
        'quality' => 100
    ],
    IMAGETYPE_PNG => [
        'load' => 'imagecreatefrompng',
        'save' => 'imagepng',
        'quality' => 0
    ],
    IMAGETYPE_GIF => [
        'load' => 'imagecreatefromgif',
        'save' => 'imagegif'
    ]
];

$title = "";

include_once('WSForm.api.include.php');
$api = new wbApi();

if( $api->isDebug() ) {
	ERROR_REPORTING(E_ALL);
	ini_set('display_errors', 1);
	wsDebug::addToDebug( '$_POST before checks', $_POST );
}

$securedVersion = $api->isSecure();


$IP = $api->app['IP'];

if( getGetString('version', false) !== false ) {
	echo getVersion();
	exit();
}

if(isset( $_GET['action']) && $_GET['action'] === 'renderWiki' ) {
    $ret = renderWiki();
    header('Content-Type: application/json');
    if (isset($_GET['pp'])) {
        echo json_encode($ret, JSON_PRETTY_PRINT);
    } else {
        echo json_encode($ret);
    }
    exit;
}

if( isset( $_GET['action'] ) && $_GET['action'] === 'handleExternalRequest' ) {
    $external = getGetString('script');
    if($external !== false) {
        // a way to try and keep unwanted requests out (v 0.8.0.1.5)
        if( isset( $_GET['mwdb'] ) && $_GET['mwdb'] !== '' ) {
            $cok = $_GET['mwdb'] . 'UserID';
            if( isset( $_COOKIE[$cok] ) && $_COOKIE[$cok] != "0" ) {
                // ok
            } else die( 'not identified' );
        } else die();
        $api = new wbApi();
        if( $api->getStatus() === false ){
	        $ret = createMsg( $api->getStatus( true ) );
        } else {
	        $res = $api->logMeIn();
	        if ( $res === false ) {
		        $ret = createMsg( $res );
	        } else {
		        $IP = $api->app['IP'];

		        if ( file_exists( $IP . '/extensions/WSForm/modules/handlers/' . basename( $external ) . '.php' ) ) {
			        include_once( $IP . '/extensions/WSForm/modules/handlers/' . basename( $external ) . '.php' );
		        } else {
		        	$ret = createMsg($i18n->wsMessage( 'wsform-external-request-not-found' ) );
		        }
	        }
        }

    } else {
        $ret = createMsg($i18n->wsMessage( 'wsform-external-request-not-found' ) );
    }
    header('Content-Type: application/json');
    if (isset($_GET['pp'])) {
        echo json_encode($ret, JSON_PRETTY_PRINT);
    } else {
        echo json_encode($ret);
    }
    exit;
}

if( isset( $_GET['action'] ) && $_GET['action'] === 'handleQuery' ) {
	$external = getGetString('handler');
	if($external !== false) {
		$extensionsFolder = getcwd()."/modules/handlers/queries/";
		if (file_exists($extensionsFolder . $external . '/query-handler.php')) {
			include_once($extensionsFolder . $external . '/query-handler.php');
		} else {
			$ret = json_encode( createMsg( $i18n->wsMessage( 'wsform-query-handler-not-found' ) ) );
		}
	} else {
		$ret = json_encode( createMsg( $i18n->wsMessage( 'wsform-query-handler-not-found' ) ) );
	}
	header('Content-Type: application/json');
	echo $ret;
	exit;
}
// Setup messages and responses

$identifier         = getPostString( 'mwidentifier' );
$messages           = new wbHandleResponses( $identifier );

if( $securedVersion ) {
	require_once( 'classes/protect.class.php' );
	$crypt = new wsform\protect\protect();
	$crypt::setCrypt( $api->getCheckSumKey() );
	$checksum = false;
	$formId = getPostString('formid' );
	if( $formId !== false ) {
		unset( $_POST['formid'] );
	}
	foreach( $_POST as $k=>$v ) {
		if( $crypt::decrypt( $k ) === 'checksum' ) {
			$checksum = unserialize( $crypt::decrypt( $v ) ) ;
			unset( $_POST[$k] );
		}
	}
	if( $checksum === false && $formId !== false ) {
		$messages->doDie( $i18n->wsMessage( 'wsform-secure-not' ) );
	}
	if( isset( $checksum[$formId]['secure'] ) ) {
		foreach( $checksum[$formId]['secure'] as $secure ) {
			$tmpName = getPostString( $secure['name'], false );
			if( $tmpName !== false ) {
				$newK = $crypt::decrypt( $secure['name'] );
				$newV = $crypt::decrypt( $tmpName );
				$delMe = $secure['name'];
				unset( $_POST[$delMe] );
				$removeList[] = $newK;
				if( substr( $newK, -2, 2 ) === '[]' ) {
					echo "ÖKOKOKOKOK";
					$newK = str_replace('[]', '', $newK );
					$_POST[$newK]= array( $newV );
				} else {
					$_POST[ $newK ] = $newV;
				}
			} else {
				$messages->doDie( $i18n->wsMessage( 'wsform-secure-fields-incomplete' ) );
			}
		}
	}
}

if( $api->isDebug() ) wsDebug::addToDebug( '$_POST after secured version check', $_POST );

$pauseBeforeRefresh = getPostString( 'mwpause' );

if( !is_cli() ) {
// check credentials
	$sessInfo = checkDefaultInformation();

	if ( $sessInfo['mwtoken'] === false ) {
		$messages->doDie( $i18n->wsMessage( 'wsform-session-no-token' ) );
		if ( isset( $_POST['mwreturn'] ) && $_POST['mwreturn'] !== "" ) {
			$messages->redirect( $_POST['mwreturn'] );
			die();
		}
	}
	if ( $sessInfo['mwsession'] === false ) {
		$messages->doDie( $i18n->wsMessage( 'wsform-session-expired' ) );
		if ( isset( $_POST['mwreturn'] ) && $_POST['mwreturn'] !== "" ) {
			$messages->redirect( $_POST['mwreturn'] );
			die();
		}
	}
	if ( $sessInfo['mwhost'] === false ) {
		$messages->doDie( $i18n->wsMessage( 'wsform-session-no-equal-host' ) );
		if ( isset( $_POST['mwreturn'] ) && $_POST['mwreturn'] !== "" ) {
			$messages->redirect( $_POST['mwreturn'] );
			die();
		}
	}
}

$captchaAction = getPostString( 'mw-captcha-action', false );
$captchaToken = getPostString( 'mw-captcha-token', false );

if( $captchaAction !== false && $captchaToken !== false ) {
    $api = new wbApi();
	if( $api->getStatus() === false ){
		$arr = array();
		$arr['msg'] = $api->getStatus( true );
		$arr['status'] = 'error';
		die();
	}
    $retCaptcha = array();
    $returnto = getPostString('mwreturn', false );
    $retCaptcha['mwreturn'] = $returnto;

    if( $returnto === false ) {
        $retCaptcha['msg'] = $i18n->wsMessage( 'wsform-noreturn-found' );
        $retCaptcha['status'] = 'error';

        $messages->handleResonse( $retCaptcha );
        die();
    }
    if( $captchaToken === '' || $captchaAction === '' ){
        $retCaptcha['msg'] = $i18n->wsMessage( 'wsform-captcha-missing-details' );
        $retCaptcha['status'] = 'error';
        $messages->handleResonse( $retCaptcha );
        die();
    }
    //secret, token, action
    $capClass = new wsform\recaptcha\render();
    $capClass::loadSettings();
    $captchaResult = $api->googleSiteVerify($capClass::$rc_secret_key, $captchaToken, $captchaAction );
    if( $captchaResult['status'] === false ) {
        $retCaptcha['msg'] = $i18n->wsMessage( 'wsform-captcha-score-to-low' ) . ' : ' . $captchaResult['results']['score'];
        $retCaptcha['status'] = 'error';
        $messages->handleResonse( $retCaptcha );
        die();
    }
}

//$wsuid = getPostString('wsuid');




// Clean all fields
if( $securedVersion ) {
	foreach( $_POST as $k=>$v ){
		if( !isWSFormSystemField( $k ) ) {
			if ( is_array( $v ) ) {
				$newArray = array();
				foreach ( $v as $multiple ) {
					$newArray[] = cleanHTML( $multiple, $k );
				}
				$_POST[ $k ] = $newArray;
			} else {
				$_POST[ $k ] = cleanHTML( $v, $k );
			}
		}
	}
}

if( $api->isDebug() ) wsDebug::addToDebug( '$_POST after cleaned html', $_POST );


$wsuid = getPostString( 'wsuid' );

// Check default variables
foreach( $_POST as $k=>$v ) {
	if( strpos( $k, 'wsdefault_' ) !== false ) {
		$tempVar = str_replace( 'wsdefault_', '', $k );
		if( !isset( $_POST[$tempVar] ) ) {
			$_POST[$tempVar] = $v;
		}
	}
}

if( $api->isDebug() ) wsDebug::addToDebug( '$_POST after wsdefault changes', $_POST );

if( $wsuid !== false ){
	unset($_POST['wsuid']);
}

if( isset( $_POST['wsform_signature'] ) ) {
	$res = signatureUpload();
	if ($res['status'] == 'error') {
		$messages->doDie( ' signature : '.$res['msg'] );
	}
	$ret = $res; // v0.7.0.3.3 added
}
if(isset($_FILES['wsformfile'])) {
	if (file_exists($_FILES['wsformfile']['tmp_name']) || is_uploaded_file($_FILES['wsformfile']['tmp_name'])) {
		$res = fileUpload();
		if ($res['status'] == 'error') {
			$messages->doDie(' file : '.$res['msg']);
		}
		$ret = $res; // v0.7.0.3.3 added
	}
}

if( isset($_POST['wsformfile_slim']) ) {
	$ret=fileUploadSlim();
	if (isset($ret['status']) && $ret['status'] === 'error') {
		$messages->doDie( ' slim : '.$ret['msg'] );
	}

}

if ( getPostString('mwaction') !== false ) {
	$action = getPostString('mwaction');
	unset( $_POST['mwaction'] );


	//print_r($_POST);

	switch ( $action ) {

		case "addToWiki" :
			$ret = saveToWiki();

 			break;

		case "get" :
			$ret = saveToWiki('get');
			if( !is_array( $ret ) && $ret !== false ) {
				$messages->redirect( $ret );
				exit;
			} elseif( $ret === false ) {
				$messages->doDie( $i18n->wsMessage( 'wsform-noreturn-found' ) );
			}
			//print_r($ret);
			//die();
			//die();
		/*
			if ( $ret ) {
				$messages->redirect( $ret );
				exit;
			} else {
				$messages->doDie( $i18n->wsMessage( 'wsform-noreturn-found' ) );
			}
		*/
			break;

		case "mail" :
			$ret = saveToWiki('yes' );
			break;
	}
}



$extension = getPostString('mwextension' );

if( $extension !== false ) {
	$extensionsFolder = getcwd()."/modules/handlers/posts/";
	if( file_exists($extensionsFolder . $extension . '/post-handler.php') ) {
		$usrt = setSummary(true);
		$mwreturn = '';
		if ( isset( $_POST['mwreturn'] ) && $_POST['mwreturn'] !== "" ) {
			$mwreturn = $_POST['mwreturn'];
		}
		$wsPostFields = setWsPostFields();
		include($extensionsFolder . $extension . '/post-handler.php');
		if ( $mwreturn !== "" ) {
			$messages->redirect( $mwreturn );
			exit;
		} elseif($identifier !== 'ajax') {
			$messages->outputMsg( $i18n->wsMessage( 'wsform-noreturn-found' ) );
		}
	}
}
if( $api->isDebug() ) {
	if ( !$api->getStatus() ) {
		wsDebug::addToDebug('API CLASS MESSAGES', $api->getStatus( true ) );
	}
	echo wsDebug::createDebugOutput();
	die('testing..');
}
//die();
if( !$api->getStatus()) { //$msg, $status="error", $mwreturn=false, $type=false
	if ( isset( $_POST['mwreturn'] ) && $_POST['mwreturn'] !== "" ) {
		$ret = createMsg( $api->getStatus( true ), 'error', $_POST['mwreturn'], "danger" );
	} else $ret = createMsg( $api->getStatus( true ), 'error', false, "danger" );
	$messages->handleResonse( $ret );
}
if( $ret !== false ) {
	$messages->handleResonse( $ret );
} else {
	die( $i18n->wsMessage( 'wsform-norequest-made' ) );
}

if ( isset( $_POST['mwreturn'] ) && $_POST['mwreturn'] !== "" ) {
	$messages->redirect( $_POST['mwreturn'] );
	exit;
} elseif($identifier !== 'ajax') {
    $messages->outputMsg( $i18n->wsMessage( 'wsform-noreturn-found' ) );
}
