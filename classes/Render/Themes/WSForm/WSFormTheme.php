<?php

namespace WSForm\Render\Themes\WSForm;

use Parser;
use PPFrame;
use WSForm\Render\Theme;
use WSForm\Render\Validate;

/**
 * Class WSFormTheme
 *
 * @package WSForm\Render
 */
class WSFormTheme implements Theme {
    /**
     * Render a WSField.
     *
     * @param string $input Parser Between beginning and end
     * @param array $args Arguments for the field
     * @param Parser $parser MediaWiki Parser
     * @param PPFrame $frame MediaWiki PPFrame
     *
     * @return array|string
     */
    public function renderField( string $input, array $args, Parser $parser, PPFrame $frame ) {
        if ( isset( $args['type'] ) ) {
            $type = $args['type'];

            if ( Validate::validInputTypes( $type ) ) {
                $parsePost = false;
                if( isset( $args['parsepost'] ) && isset( $args['name'] )) {
                    $parsePost = true;
                    $parseName = $args['name'];
                    unset( $args['parsepost'] );
                }
                $type = "render_" . $type;
                unset( $args['type'] );
                $noParse = false;
                if ( method_exists( 'wsform\field\render', $type ) ) {

                    foreach ( $args as $k => $v ) {
                        if ( ( strpos( $v, '{' ) !== false ) && ( strpos( $v, '}' ) !== false ) ) {
                            $args[ $k ] = $parser->recursiveTagParse( $v, $frame );
                        }
                        if( $k === 'noparse' ) {
                            $noParse = true;
                        }
                    }

                    //Test to see if this gets parsed
                    if( $noParse === false ) {
                        $input = $parser->recursiveTagParse($input, $frame);
                    }
                    //End test
                    if ( $type == 'render_option' || $type == 'render_file' || $type == 'render_submit' || $type == 'render_text' || $type == 'render_textarea') {
                        $ret = Field::$type( $args, $input, $parser, $frame );
                    } else {
                        $ret = Field::$type( $args, $input );
                    }
                } else {
                    $ret = $type . " is unknown";
                }

                if( $parsePost === true ) {
                    $ret .= '<input type="hidden" name="wsparsepost[]" value="' . $parseName . "\">\n";
                }
                //self::addInlineJavaScriptAndCSS();

                return array( $ret, "markerType" => 'nowiki');
            } else return array( wfMessage( "wsform-field-invalid" )->text() . ": " . $type, "markerType" => 'nowiki');
        } else {
            return array( wfMessage( "wsform-field-invalid" )->text(), "markerType" => 'nowiki');
        }
    }

    /**
     * Render a WSEdit.
     *
     * @param string $input Parser Between beginning and end
     * @param array $args Arguments for the field
     * @param Parser $parser MediaWiki Parser
     * @param PPFrame $frame MediaWiki PPFrame
     *
     * @return array|string
     */
    public function renderEdit( string $input, array $args, Parser $parser, PPFrame $frame ) {
        foreach ( $args as $k => $v ) {
            if ( ( strpos( $v, '{' ) !== false ) && ( strpos( $v, "}" ) !== false ) ) {
                $args[ $k ] = $parser->recursiveTagParse( $v, $frame );
            }
        }

        $ret = Edit::render_edit( $args );
        //self::addInlineJavaScriptAndCSS();
        return array( $ret, 'noparse' => true, "markerType" => 'nowiki' );
    }

    /**
     * Render a WSCreate.
     *
     * @param string $input Parser Between beginning and end
     * @param array $args Arguments for the field
     * @param Parser $parser MediaWiki Parser
     * @param PPFrame $frame MediaWiki PPFrame
     *
     * @return array|string
     */
    public function renderCreate( string $input, array $args, Parser $parser, PPFrame $frame ) {
        foreach ( $args as $k => $v ) {
            if ( ( strpos( $v, '{' ) !== false ) && ( strpos( $v, "}" ) !== false ) ) {
                $args[ $k ] = $parser->recursiveTagParse( $v, $frame );
            }
        }
        $ret = wsform\create\render::render_create( $args );
        //self::addInlineJavaScriptAndCSS();
        return array( $ret, 'noparse' => true, "markerType" => 'nowiki' );
    }

    /**
     * Render a WSEmail.
     *
     * @param string $input Parser Between beginning and end
     * @param array $args Arguments for the field
     * @param Parser $parser MediaWiki Parser
     * @param PPFrame $frame MediaWiki PPFrame
     *
     * @return array|string
     */
    public function renderEmail( string $input, array $args, Parser $parser, PPFrame $frame ) {
        $args['content'] = base64_encode( $parser->recursiveTagParse( $input, $frame ) );
        foreach ( $args as $k => $v ) {
            if ( ( strpos( $v, '{' ) !== false ) && ( strpos( $v, "}" ) !== false ) ) {
                $args[ $k ] = $parser->recursiveTagParse( $v, $frame );
            }
        }
        $ret = wsform\mail\render::render_mail( $args );
        //self::addInlineJavaScriptAndCSS();
        return array( $ret, 'noparse' => true, "markerType" => 'nowiki' );
    }

    /**
     * Render a WSInstance.
     *
     * @param string $input Parser Between beginning and end
     * @param array $args Arguments for the field
     * @param Parser $parser MediaWiki Parser
     * @param PPFrame $frame MediaWiki PPFrame
     *
     * @return array|string
     */
    public function renderInstance(string $input, array $args, Parser $parser, PPFrame $frame) {
        global $IP, $wgScript;
        $realUrl = str_replace( '/index.php', '', $wgScript );


        // Add move, delete and add button with classes
        $parser->getOutput()->addModuleStyles( 'ext.wsForm.Instance.styles' );

        if( ! \wsform\wsform::isLoaded( 'wsinstance-initiated' ) ) {
            wsform\wsform::addAsLoaded( 'wsinstance-initiated' );
        }

        $output = $parser->recursiveTagParse( $input, $frame );

        if( ! \wsform\wsform::isLoaded( 'wsinstance-initiated' ) ) {
            wsform\wsform::addAsLoaded( 'wsinstance-initiated' );
        }

        $ret = wsform\instance\render::render_instance( $args, $output );

        wsform\wsform::removeAsLoaded( 'wsinstance-initiated' );

        if(! wsform\wsform::isLoaded( 'multipleinstance' ) ) {
            if ( file_exists( $IP . '/extensions/WSForm/modules/instances/wsInstance.js' ) ) {
                $ls =  $realUrl . '/extensions/WSForm/modules/instances/wsInstance.js';
                $ret = '<script type="text/javascript" charset="UTF-8" src="' . $ls . '"></script>' . $ret ;
                //wsform\wsform::includeInlineScript( $ls );
                //$parser->getOutput()->addModules( ['ext.wsForm.instance'] );
                wsform\wsform::addAsLoaded( 'multipleinstance' );
            }
        }





        return array( $ret, 'noparse' => true, "markerType" => 'nowiki' );
    }

    /**
     * Render a WSForm.
     *
     * @param string $input Parser Between beginning and end
     * @param array $args Arguments for the field
     * @param Parser $parser MediaWiki Parser
     * @param PPFrame $frame MediaWiki PPFrame
     *
     * @return array|string
     */
    public function renderForm(string $input, array $args, Parser $parser, PPFrame $frame) {
        global $wgUser, $wgEmailConfirmToEdit, $IP, $wgScript;
        \wsform\wsform::$chkSums = array();
        $anon = false;
        $ret = '';
        wsform\wsform::$formId = uniqid();

        // Set i18n general messages
        wsform\wsform::$msg_unverified_email = wfMessage( "wsform-unverified-email1" )->text() . wfMessage( "wsform-unverified-email2" )->text();
        wsform\wsform::$msg_anonymous_user = wfMessage( "wsform-anonymous-user" )->text();

        $parser->getOutput()->addModuleStyles( 'ext.wsForm.general.styles' );

        // Do we have messages to show
        if ( isset( $args['showmessages'] ) ) {


            if ( isset ( $_COOKIE['wsform'] ) ) {
                $ret = '<div class="wsform alert-' . $_COOKIE['wsform']['type'] . '">' . $_COOKIE['wsform']['txt'] . '</div>';
                setcookie( "wsform[type]", "", time() - 3600, '/' );
                setcookie( "wsform[txt]", "", time() - 3600, '/' );

                return array( $ret, 'noparse' => true, "markerType" => 'nowiki' );
            } else {
                return "";
            }
        }

        if ( isset( $args['restrictions'] ) ) {
            if ( ( strpos( $args['restrictions'], '{' ) !== false ) && ( strpos( $args['restrictions'], "}" ) !== false ) ) {
                $args['restrictions'] = $parser->recursiveTagParse( $args['restrictions'], $frame );
            }
            if ( strtolower( $args['restrictions'] ) === 'lifted' ) {
                $anon = true;
            }
        }

        //TODO: Will be deprecated in 1.36. As off 1.34 use isRegistered()
        if ( ! $wgUser->isLoggedIn() && $anon === false ) {
            $ret = wsform\wsform::$msg_anonymous_user;
            return $ret;
        }

        if ( isset( $args['id'] ) && $args['id'] !== '' ) {
            $formId =  $args['id'];
        } else $formId = false;

        if ( isset( $args['loadscript'] ) && $args['loadscript'] !== '' ) {
            if(! wsform\wsform::isLoaded($args['loadscript'])) {
                if ( file_exists( $IP . '/extensions/WSForm/modules/customJS/loadScripts/' . $args['loadscript'] . '.js' ) ) {
                    $ls = file_get_contents( $IP . '/extensions/WSForm/modules/customJS/loadScripts/' . $args['loadscript'] . '.js' );
                    if ( $ls !== false ) {
                        //$loadScript = "<script>" . $ls . "</script>\n";

                        if( $formId !== false ) {
                            $k = 'wsForm_' . $args['loadscript'];
                            $v = $formId;
                            wsform\wsform::includeJavaScriptConfig( $k, $v );
                        }
                        wsform\wsform::includeInlineScript( $ls );
                        wsform\wsform::addAsLoaded( $args['loadscript'] );
                    }
                }
            }
        } else {
            $loadScript = false;
        }

        /* No idea why this is in here, but makes no sense.
                if ( isset( $args['action'] ) && $args['action'] == 'get' ) {
                    $anon = true;
                }
        */



        $noEnter = false;
        if ( isset( $args['no_submit_on_return'] ) ) {
            if(! wsform\wsform::isLoaded('keypress') ) {
                $noEnter = "$(document).on('keyup keypress', 'form input[type=\"text\"]', function(e) {
            if(e.keyCode == 13) {
              e.preventDefault();
              return false;
            }
          });$(document).on('keyup keypress', 'form input[type=\"search\"]', function(e) {
            if(e.keyCode == 13) {
              e.preventDefault();
              return false;
            }
          });$(document).on('keyup keypress', 'form input[type=\"password\"]', function(e) {
            if(e.keyCode == 13) {
              e.preventDefault();
              return false;
            }
          })";
                wsform\wsform::includeInlineScript( $noEnter );
                wsform\wsform::addAsLoaded( 'keypress' );
            }
        }

        if ( isset( $args['action'] ) && $args['action'] == 'addToWiki' && $anon === false ) {
            if ( $wgEmailConfirmToEdit === true && ! $wgUser->isEmailConfirmed() ) {
                $ret = wsform\wsform::$msg_unverified_email;

                return $ret;
            }
        }
        if ( isset( $args['changetrigger'] ) && $args['changetrigger'] !== '' && isset($args['id'])) {
            $onchange = "";
            $changeId = $args['id'];
            $changeCall = $args['changetrigger'];
            $onchange = "$('#" . $changeId . "').change(" . $changeCall . "(this));";
            wsform\wsform::includeInlineScript( $onchange );
        } else $onchange = false;

        if( isset( $args['messageonsuccess']) && $args['messageonsuccess'] !== '' ) {
            $msgOnSuccessJs = $js = 'var mwonsuccess = "' . $args['messageonsuccess'] . '";';
            wsform\wsform::includeInlineScript( $msgOnSuccessJs );
        } else $msgOnSuccessJs = '';

        if( isset( $args['show-on-select' ] ) ) {
            \wsform\wsform::setShowOnSelectActive();
            $input = \wsform\wsform::checkForShowOnSelectValue( $input );
        }

        $output = $parser->recursiveTagParse( $input, $frame );
        foreach ( $args as $k => $v ) {
            if ( ( strpos( $v, '{' ) !== false ) && ( strpos( $v, "}" ) !== false ) ) {
                $args[ $k ] = $parser->recursiveTagParse( $v, $frame );
            }
        }
        if (wsform\wsform::getRun() === false) {
            $realUrl = str_replace( '/index.php', '', $wgScript );
            $ret = '<script type="text/javascript" charset="UTF-8" src="' . $realUrl . '/extensions/WSForm/WSForm.general.js"></script>' . "\n";
            wsform\wsform::setRun(true);
        }
        $ret .= wsform\form\render::render_form( $args, $parser->getTitle()->getLinkURL() );

        //Add checksum

        if( \wsform\wsform::isShowOnSelectActive() ) {
            $ret .= \wsform\wsform::createHiddenField( 'showonselect', '1' );

        }

        if( \wsform\wsform::$secure ) {
            \wsform\protect\protect::setCrypt( \wsform\wsform::$checksumKey );
            if( \wsform\wsform::$runAsUser ) {
                $chcksumwuid = \wsform\protect\protect::encrypt( 'wsuid' );
                $uid = \wsform\protect\protect::encrypt( $wgUser->getId() );
                \wsform\wsform::addCheckSum( 'secure', $chcksumwuid, $uid, "all" );
                $ret          .= '<input type="hidden" name="' . $chcksumwuid . '" value="' . $uid . '">';
            }
            $chcksumName = \wsform\protect\protect::encrypt( 'checksum' );
            if( !empty( \wsform\wsform::$chkSums ) ) {
                $chcksumValue = \wsform\protect\protect::encrypt( serialize( \wsform\wsform::$chkSums ) );
                $ret          .= '<input type="hidden" name="' . $chcksumName . '" value="' . $chcksumValue . '">';
                $ret          .= '<input type="hidden" name="formid" value="' . \wsform\wsform::$formId . '">';
            }

        }




        $ret .= $output . '</form>';

        if( isset( $args['recaptcha-v3-action'] ) && ! wsform\wsform::isLoaded( 'google-captcha' ) ) {
            $tmpCap = wsform\recaptcha\render::render_reCaptcha();
            if( $tmpCap !== false ) {
                wsform\wsform::addAsLoaded( 'google-captcha' );
                $ret = $tmpCap . $ret;
            }
        }

        if( wsform\wsform::$reCaptcha !== false  ) {
            if( !isset( $args['id']) || $args['id'] === '' ) {
                $ret = wfMessage( "wsform-recaptcha-no-form-id" )->text();
                return $ret;
            }
            if ( file_exists( $IP . '/extensions/WSForm/modules/recaptcha.js' ) ) {
                $rcaptcha = file_get_contents( $IP . '/extensions/WSForm/modules/recaptcha.js' );
                $replace = array(
                    '%%id%%',
                    '%%action%%',
                    '%%sitekey%%',
                );
                $with = array(
                    $args['id'],
                    wsform\wsform::$reCaptcha,
                    wsform\recaptcha\render::$rc_site_key
                );
                $rcaptcha = str_replace( $replace, $with, $rcaptcha );
                wsform\wsform::includeInlineScript( $rcaptcha );
                wsform\wsform::$reCaptcha = false;
            } else {
                $ret = wfMessage( "wsform-recaptcha-no-js" )->text();
                return $ret;
            }
        }
        //echo "<pre>";
        // print_r( \wsform\wsform::$chkSums );
        // echo "</pre>";
        //print_r( \wsform\wsform::$secure );
        //print_r( wsform\wsform::getJavaScriptConfigToBeAdded() );

        //echo "<pre>";
        //print_r( wsform\wsform::getJavaScriptConfigToBeAdded() ) ;
        //echo "</pre>";
        self::addInlineJavaScriptAndCSS();
        return array( $ret, "markerType" => 'nowiki' );
    }

    /**
     * Render a WSFieldset.
     *
     * @param string $input Parser Between beginning and end
     * @param array $args Arguments for the field
     * @param Parser $parser MediaWiki Parser
     * @param PPFrame $frame MediaWiki PPFrame
     *
     * @return array|string
     */
    public function renderFieldset( string $input, array $args, Parser $parser, PPFrame $frame ) {
        $ret = '<fieldset ';
        foreach ( $args as $k => $v ) {
            if ( wsform\validate\validate::validParameters( $k ) ) {
                $ret .= $k . '="' . $v . '" ';
            }
        }
        $output = $parser->recursiveTagParse( $input, $frame );
        $ret    .= '>' . $output . '</fieldset>';
        //self::addInlineJavaScriptAndCSS();
        return array( $ret, "markerType" => 'nowiki' );
    }

    /**
     * Render a WSSelect.
     *
     * @param string $input Parser Between beginning and end
     * @param array $args Arguments for the field
     * @param Parser $parser MediaWiki Parser
     * @param PPFrame $frame MediaWiki PPFrame
     *
     * @return array|string
     */
    public function renderSelect( string $input, array $args, Parser $parser, PPFrame $frame ) {
        $ret = '<select ';


        foreach ( $args as $k => $v ) {
            if ( wsform\validate\validate::validParameters( $k ) ) {
                if ( $k == "name" && strpos( $v, '[]' ) === false ) {
                    $name = $v;
                    $v    .= '[]';
                }
                $ret .= $k . '="' . $parser->recursiveTagParse( $v, $frame ) . '" ';
            }
        }
        $output = $parser->recursiveTagParse( $input, $frame );

        $ret .= '>';
        if ( isset( $args['placeholder'] ) ) {
            $ret .= '<option value="" disabled selected>' . $args['placeholder'] . '</option>';
        }
        $ret .=  $output . '</select>';

        //self::addInlineJavaScriptAndCSS();
        return array( $ret, "markerType" => 'nowiki' );
    }

    /**
     * Render a WSToken.
     *
     * @param string $input Parser Between beginning and end
     * @param array $args Arguments for the field
     * @param Parser $parser MediaWiki Parser
     * @param PPFrame $frame MediaWiki PPFrame
     *
     * @return array|string
     */
    public function renderToken( string $input, array $args, Parser $parser, PPFrame $frame ) {
        global $wgOut, $IP, $wgDBname, $wgDBprefix;

        if( isset ( $wgDBprefix ) && !empty($wgDBprefix) ) {
            $prefix = '_' . $wgDBprefix;
        } else $prefix = '';

        //$parser->disableCache();
        //$parser->getOutput()->addModules( 'ext.wsForm.select2.kickstarter' );
        $ret         = '<select data-inputtype="ws-select2"';
        $placeholder = false;
        $allowtags = false;
        $onlyone = false;
        $multiple = false;


        foreach ( $args as $k => $v ) {
            if ( wsform\validate\validate::validParameters( $k ) ) {
                if ( $k == 'placeholder' ) {
                    $placeholder = $parser->recursiveTagParse( $v, $frame );
                } elseif( strtolower( $k ) === "multiple") {
                    $multiple = $parser->recursiveTagParse( $v, $frame );
                    if ( $multiple === "multiple" ) {
                        $ret .= 'multiple="multiple" ';
                    }
                } elseif( strtolower( $k ) === 'id' &&  \wsform\wsform::isLoaded( 'wsinstance-initiated' ) ) {
                    $ret .= 'data-wsselect2id="' . $v . '"';
                } else {
                    $ret .= $k . '="' . $parser->recursiveTagParse( $v, $frame ) . '" ';
                }
            }
        }

        $output = $parser->recursiveTagParse( $input );
        $id   = $parser->recursiveTagParse( $args['id'], $frame );

        $ret    .= '>';
        if( $placeholder !== false ){
            $ret .= '<option></option>';
        }
        $ret .= $output . '</select>' . "\n";
        $out    = "";
        if ( ! \wsform\wsform::isLoaded( 'wsinstance-initiated' ) ){
            $out    .= '<input type="hidden" id="select2options-' . $id . '" value="';
        } else {
            $out    .= '<input type="hidden" data-wsselect2options="select2options-' . $id . '" value="';
        }

        if( isset( $args['input-length-trigger'] ) && $args['input-length-trigger' !== '' ] ) {
            $iLength = trim( $args['input-length-trigger'] );
        } else $iLength = 3;

        if ( isset( $args['json'] ) && isset( $args['id'] ) ) {
            if ( strpos( $args['json'], 'semantic_ask' ) ) {
                $json = $args['json'];
            } else {
                $json = $parser->recursiveTagParse( $args['json'], $frame );
            }
            $out .= "var jsonDecoded = decodeURIComponent( '" . urlencode( $json ) . "' );\n";
        }


        $out .= "$('#" . $id . "').select2({";

        $callb = '';

        $mwdb = $wgDBname . $prefix;

        if ( $placeholder !== false ) {
            $out .= "placeholder: '" . $placeholder . "',";
        }

        if ( isset( $args['json'] ) && isset( $args['id'] ) ) {

            $out .= "\ntemplateResult: testSelect2Callback,\n";
            $out .= "\nescapeMarkup: function (markup) { return markup; },\n";
            $out .= "\nminimumInputLength: $iLength,\n";
            $out .= "\najax: { url: jsonDecoded, delay:500, dataType: 'json',"."\n";
            $out .= "\ndata: function (params) { var queryParameters = { q: params.term, mwdb: '".$mwdb."' }\n";
            $out .= "\nreturn queryParameters; }}";
            $callb= '';
            if ( isset( $args['callback'] ) ) {
                if ( isset( $args['template'] ) ) {
                    $templ = ", '" . $args['template'] . "'";
                } else $templ = '';
                $cb  = $parser->recursiveTagParse( $args['callback'], $frame );
                $callb = "$('#" . $id . "').on('select2:select', function(e) { " . $cb . "('" . $id . "'" . $templ . ")});\n";
                $callb .= "$('#" . $id . "').on('select2:unselect', function(e) { " . $cb . "('" . $id . "'" . $templ . ")});\n";
            }
        }
        if( isset( $args['allowtags'] ) ) {
            if ( isset( $args['json'] ) && isset( $args['id'] ) ) {
                $out .= ",\ntags: true";
            } else {
                $out .= "\ntags: true";
            }
        }
        if( isset( $args['allowclear'] ) && isset( $args['placeholder'] ) ) {
            if ( ( isset( $args['json'] ) ) || isset( $args['allowtags'] ) ) {
                $out .= ",\nallowClear: true";
            } else {
                $out .= "\nallowClear: true";
            }
        }

        /*
                if( $multiple !== false && strtolower( $multiple ) === "multiple" ) {

                    if ( ( isset( $args['json'] ) && isset( $args['id'] ) ) || isset( $args['allowtags'] ) || isset( $args['allowclear'] ) ) {
                        $out .= ",\nmultiple: true";
                    } else {
                        $out .= "\nmultiple: true";
                    }
                } else {
                    if ( ( isset( $args['json'] ) && isset( $args['id'] ) ) || isset( $args['allowtags'] ) || isset( $args['allowclear'] ) ) {
                        $out .= ",\nmultiple: false";
                    } else {
                        $out .= "\nmultiple: false";
                    }
                }
        */
        $out .= '});';
        $callb .= "$('select').trigger('change');\"\n";
        $out .= $callb . ' />';
        $lcallback = '';
        if(isset($args['loadcallback'])) {
            if(! wsform\wsform::isLoaded($args['loadcallback'] ) ) {
                if ( file_exists( $IP . '/extensions/WSForm/modules/customJS/wstoken/' . $args['callback'] . '.js' ) ) {
                    $lf  = file_get_contents( $IP . '/extensions/WSForm/modules/customJS/wstoken/' . $args['callback'] . '.js' );
                    $lcallback = "<script>$lf</script>\n";
                    wsform\wsform::includeInlineScript( $lf );
                    wsform\wsform::addAsLoaded( $args['loadcallback'] );
                }
            }
        }
        $attach = "<script>wachtff(attachTokens, true );</script>";
        //wsform\wsform::includeInlineScript( 'document.addEventListener("DOMContentLoaded", function() { wachtff(attachTokens, true); }, false);' );
        //$wgOut->addHTML( $out );

        $ret = $ret . $out . $attach;
        self::addInlineJavaScriptAndCSS();
        return array( $ret, "markerType" => 'nowiki' );
    }

    /**
     * Render a WSLegend.
     *
     * @param string $input Parser Between beginning and end
     * @param array $args Arguments for the field
     * @param Parser $parser MediaWiki Parser
     * @param PPFrame $frame MediaWiki PPFrame
     *
     * @return array|string
     */
    public function renderLegend( string $input, array $args, Parser $parser, PPFrame $frame ) {
        $ret = '<legend ';
        if ( isset( $args['class'] ) ) {
            $ret .= ' class="' . $args['class'] . '" ';
        }
        if ( isset( $args['align'] ) ) {
            $ret .= ' align="' . $args['align'] . '"';
        }
        $ret .= '>' . $input . '</legend>';
        //self::addInlineJavaScriptAndCSS();
        return array( $ret, "markerType" => 'nowiki' );
    }

    /**
     * Render a WSLabel.
     *
     * @param string $input Parser Between beginning and end
     * @param array $args Arguments for the field
     * @param Parser $parser MediaWiki Parser
     * @param PPFrame $frame MediaWiki PPFrame
     *
     * @return array|string
     */
    public function renderLabel( string $input, array $args, Parser $parser, PPFrame $frame ) {
        $ret = '<label ';
        foreach ( $args as $k => $v ) {
            if ( wsform\validate\validate::validParameters( $k ) ) {
                if ( ( strpos( $v, '{' ) !== false ) && ( strpos( $v, '}' ) !== false ) ) {
                    $v = $parser->recursiveTagParse( $v, $frame );
                }
                $ret .= $k . '="' . $v . '" ';
            }
        }

        $output = $parser->recursiveTagParse( $input, $frame );
        $ret    .= '>' . $output . '</label>';
        //self::addInlineJavaScriptAndCSS();
        return array( $ret, "markerType" => 'nowiki' );
    }
}