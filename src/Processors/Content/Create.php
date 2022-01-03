<?php
/**
 * Created by  : Designburo.nl
 * Project     : wsformWikiBaseNL
 * Filename    : create.class.php
 * Description :
 * Date        : 19-3-2021
 * Time        : 21:22
 */

namespace WSForm\Processors\Content;

use WSForm\Processors\Definitions;
use WSForm\Processors\Content\ContentCore;
use WSForm\WSFormException;

class create {

	private static $ret;

	/**
	 * @throws WSFormException
	 */
	public static function writePage() {

		$fields = ContentCore::getFields();

		self::$ret = ContentCore::createContent();

		if (strpos( $fields['writepage'],'[') !== false) {
			$fields['writepage'] = ContentCore::parseTitle( $fields['writepage'] );
		}

		$title = $fields['writepage'];

		if( strtolower( $fields['option'] ) == 'next_available' ) {
			// get highest number
			$hnr = ContentCore::getNextAvailable( $title );
			if( $hnr['status'] !== 'error') {
				$title = $fields['writepage'] . $hnr['result'];
			} else {
				throw new WSFormException( $hnr['message'] );
				//return wbHandleResponses::createMsg( $hnr['message'], 'error', $returnto);
			}
			//$title = $writepage . $api->getNextAvailable( $title );
			//die( $title );
			//$title = $writepage . $api->getWikiListNumber($title);
			if( $title === false ) {
				throw new WSFormException( wfMessage( 'wsform-mwcreate-wrong-title2' )->text() );
				//return wbHandleResponses::createMsg($i18n->wsMessage( 'wsform-mwcreate-wrong-title2' ), 'error', $returnto);
			}
		}
		if ( substr( strtolower( $fields['option'] ) ,0,6 ) === 'range:' ) {
			$range = substr( $fields['option'],6 );
			$rangeCheck = explode('-', $range);

			if( !ctype_digit( $rangeCheck[0] ) || !ctype_digit( $rangeCheck[1] ) ) {
				throw new WSFormException( wfMessage( 'wsform-mwoption-bad-range' ) );
				//return wbHandleResponses::createMsg($i18n->wsMessage( 'wsform-mwoption-bad-range' ), 'error', $returnto);
			}

			//$startRange = (int)$range[0];
			//$endRange = (int)$range[1];


			//$tmp  = $api->getWikiListNumber($title, array('start' => $startRange, 'end' => $endRange) );
			$tmp  = ContentCore::getFromRange( $title, $range );
			if( $tmp['status'] === 'error') {
				//echo $tmp['message'];
				throw new WSFormException( $tmp['message'] );
				//return wbHandleResponses::createMsg( $tmp['message'], 'error', $returnto);
			}
			$tmp = $tmp['result'];
			/*
			if($tmp === false) {
				return wbHandleResponses::createMsg($i18n->wsMessage('wsform-mwoption-out-of-range'), 'error', $returnto);
			}
			*/
			if( $fields['leadByZero'] === true ) {
				$endrangeLength = strlen($range[1]);
				$tmp = str_pad($tmp, $endrangeLength, '0', STR_PAD_LEFT);
			}
			$title = $fields['writepage'] . $tmp;
		}

		if ( $fields['option'] == 'add_random' && $fields['writepage'] !== false ) {
			$title = $fields['writepage'] . ContentCore::createRandom();
		}


		if ( ! $fields['writepage'] ) {
			throw new WSFormException( wsMessage( 'wsform-mwcreate-wrong-title')->text() );
			//return wbHandleResponses::createMsg( $i18n->wsMessage( 'wsform-mwcreate-wrong-title') );

		}
		// Now add the page to the wiki


		//$api->usr = $etoken;
		$api->logMeIn();
		//die($wsuid);

		$result = $api->savePageToWiki( $title, $ret, $summary );
		if(isset($result['received']['error'])) {
			return wbHandleResponses::createMsg($result['received']['error'],'error',$returnto);
		}
		if( $mwfollow !== false ) {
			if( $mwfollow === 'true' ) {

				$returnto = $api->app['wgScript'] . '/' . $title;
			} else {
				if( strpos( $returnto, '?' ) ) {
					$returnto = $returnto . '&' . $mwfollow . '=' . $title;
				} else {
					$returnto = $returnto . '?' . $mwfollow . '=' . $title;
				}
			}
		}
		$weHaveApi = true;


	}


}