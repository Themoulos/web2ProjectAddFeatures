<?php
/**
 * This class encapsulates the PHP mail() function.
 *
 * Example
 * <code>
 * $m = new w2p_Utilities_Mail(); // create the mail
 * $m->From( "leo@isp.com" );
 * $m->To( "destination@somewhere.fr" );
 * $m->Subject( "the subject of the mail" );
 *
 * $message= "Hello world!\nthis is a test of the Mail class\nplease ignore\nThanks.";
 * $m->Body( $message);    // set the body
 * $m->Cc( "someone@somewhere.fr");
 * $m->Bcc( "someoneelse@somewhere.fr");
 * $m->Priority(4) ;    // set the priority to Low
 * $m->Attach( "/home/leo/toto.gif", "image/gif" ) ;    // attach a file of type image/gif
 * $m->Send();    // send the mail
 * echo "the mail below has been sent:<br><pre>", $m->Get(), "</pre>";
 * </code>
 *
 * @package     web2project\utilities
 * @author      Leo West - lwest@free.fr
 * @author      Emiliano Gabrielli - emiliano.gabrielli@dearchitettura.com
 * @author      Pedro Azevedo - pedroa@web2project.net
 */

class w2p_Utilities_Mail extends PHPMailer
{

    /**
     *    list of To addresses
     *    @var    array
     */
    public $ato = array();

    /**
     *    @var    array
     */
    public $acc = array();

    /**
     *    @var    array
     */
    public $abcc = array();
    /**
     *    paths of attached files
     *    @var array
     */

    /**
     *    character set of message
     *    @var string
     */
    public $receipt = false;
    public $useRawAddress = true;
    public $defer;

    /**
     *    Mail constructor
     */
    public function __construct()
    {
        $this->defer = w2PgetConfig('mail_defer');
        $this->canEncode = function_exists('imap_8bit') && 'us-ascii' != $this->charset;
        $this->hasMbStr = function_exists('mb_substr');

        $this->Mailer = (w2PgetConfig('mail_transport', 'php') == 'smtp' ? 'smtp' : 'mail');
        $this->Port = w2PgetConfig('mail_port', '25');
        $this->Host = w2PgetConfig('mail_host', 'localhost');
        $this->Hostname = w2PgetConfig('mail_host', 'localhost');
        $this->SMTPAuth = w2PgetConfig('mail_auth', false);
        $this->SMTPSecure = w2PgetConfig('mail_secure', '');
        $this->SMTPDebug = w2PgetConfig('mail_debug', false);
        $this->Username = w2PgetConfig('mail_user');
        $this->Password = w2PgetConfig('mail_pass');
        $this->Timeout = w2PgetConfig('mail_timeout', 0);
        $this->charset = 'utf-8';
        $this->Encoding = $this->charset != 'us-ascii' ? '8bit' : '7bit';
        //The from clause is fixed for all emails so that the users1 do not reply to one another
        $this->From(w2PgetConfig('mail_user', 'webmaster@'.w2PgetConfig('site_domain')), w2PgetConfig('company_name'));
    }

    /** @deprecated since 3.2*/
    public function autoCheck() {   return true;    }

    /**
     *    Define the subject line of the email
     *    @param string $subject any monoline string
     */
    public function Subject($subject)
    {
        $this->Subject = w2PgetConfig('email_prefix') . ' ' . $subject;
        return true;
    }

    /**
     *    set the sender of the mail
     *    @param string $from should be an email address
     */
    public function From($from, $fromname = '')
    {
        if (!is_string($from)) {
            return false;
        }
        $this->From = $from;
        $this->FromName = $fromname;
        if ($this->receipt) {
            $this->ConfirmReadingTo($from);
        }
        return true;
    }

    /**
     *    set the Reply-to header
     *    @param string $email should be an email address
     */
    public function ReplyTo($address)
    {
        if (!is_string($address)) {
            return false;
        }
        $this->AddReplyTo($address);
        if ($this->receipt) {
            $this->ConfirmReadingTo($address);
        }
        return true;
    }

    /**
     *    add a receipt to the mail ie.  a confirmation is returned to the "From" address (or "ReplyTo" if defined)
     *    when the receiver opens the message.
     *    @warning this functionality is *not* a standard, thus only some mail clients are compliants.
     */
    public function Receipt()
    {
        $this->receipt = true;
        return true;
    }

    /**
     *    set the mail recipient
     *
     *    The optional reset parameter is useful when looping through records to send individual mails.
     *    This prevents the 'to' array being continually stacked with additional addresses.
     *
     *    @param string $to email address, accept both a single address or an array of addresses
     *    @param boolean $reset resets the current array
     */
    public function To($to, $reset = false)
    {
        if (is_array($to)) {
            $this->ato = $to;
        } else {
            if (preg_match("/^(.*)\<(.+)\>$/D", $to, $regs)) {
                $to = $regs[2];
            }
            if ($reset) {
                unset($this->ato);
                $this->ato = array();
                $this->ClearAddresses();
                $this->ClearAttachments();
            }
            $this->ato[] = $to;
        }

        $this->CheckAddresses($this->ato);

        foreach ($this->ato as $to_address) {
            if (strpos($to_address, '<') !== false) {
                preg_match('/^.*<([^@]+\@[a-z0-9\._-]+)>/i', $to_address, $matches);
                if (isset($matches[1])) {
                    $to_address = $matches[1];
                }
            }
            $this->AddAddress($to_address);
        }
        return true;
    }

    /**
     *    Cc()
     *    set the CC headers ( carbon copy )
     *    $cc : email address(es), accept both array and string
     */
    public function Cc($cc)
    {

        $this->acc = (is_array($cc)) ? explode(',', $cc) : $cc;

        $this->CheckAddresses($this->acc);

        foreach ($this->acc as $cc_address) {
            if (strpos($cc_address, '<') !== false) {
                preg_match('/^.*<([^@]+\@[a-z0-9\._-]+)>/i', $cc_address, $matches);
                if (isset($matches[1])) {
                    $cc_address = $matches[1];
                }
            }
            $this->AddCC($cc_address);
        }

        return true;
    }

    /**
     *    set the Bcc headers ( blank carbon copy ).
     *    $bcc : email address(es), accept both array and string
     */
    public function Bcc($bcc)
    {

        $this->abcc = (is_array($bcc)) ? explode(',', $bcc) : $bcc;

        $this->CheckAddresses($this->abcc);

        foreach ($this->abcc as $bcc_address) {
            if (strpos($bcc_address, '<') !== false) {
                preg_match('/^.*<([^@]+\@[a-z0-9\._-]+)>/i', $bcc_address, $matches);
                if (isset($matches[1])) {
                    $bcc_address = $matches[1];
                }
            }
            $this->AddCC($bcc_address);
        }

        return true;
    }

    /**
     *        set the body (message) of the mail
     *        define the charset if the message contains extended characters (accents)
     *        default to us-ascii
     *        $mail->Body( "m?l en fran?ais avec des accents", "iso-8859-1" );
     */
    public function Body($body, $charset = '')
    {
        $this->Body = w2PHTMLDecode($body);

        if (!empty($charset)) {
            @($this->charset = strtolower($charset));
            if ($this->charset != 'us-ascii') {
                $this->Encoding = '8bit';
            }
        }
    }

    /**
     *  set the Organization header
     */
    public function Organization($org)
    {
        if ('' != trim($org)) {
            $this->xheaders['Organization'] = $this->_wordEncode($org, mb_strlen('Organization: '));
        }
    }

    /**
     *        set the mail priority
     *        $priority : integer taken between 1 (highest) and 5 ( lowest )
     *        ex: $mail->Priority(1) ; => Highest
     */
    public function Priority($priority = 5)
    {
        $priority = max(1, (int) $priority);
        $priority = min(5, $priority);

        $this->Priority = $priority;
        return true;
    }

    /**
     *    Overload the Send method from PHPMailer to provide deferred mails
     *    @access public
     */
    public function Send()
    {
        try {
            if ($this->defer) {
                return $this->QueueMail();
            } else {
                return PHPMailer::Send();
            }
        } catch (Exception $exc) {
            error_log($exc->getMessage());
        }
    }

    /**
     *    SendSeparatelyTo is a workaround method to provide a way to send emails to a set of addresses in a separate way.
     *    PHPMailer does not support this natively so we have to workaround it.
     *    It picks the $to array parameter first, if it is not present, it will try to pick the $ato array property from this class
     *    that has been filled by calls to the To method.
     *    If you don't want the emails to be sent to each recipient individually, you should use the To and then the Send method instead.
     *    The To method stacks recipients in the ato array property, and Send sends them all in one email only.
     *    The SendSeparatelyTo method sends one email per recipient, and only one recipient shows in the To field.
     * 
     *    @param  $to array with email addresses
     *    @access public
     */
    public function SendSeparatelyTo($to = array())
    {
        if (is_array($to) && count($to)) {
            $newArray = array_flip($to) + array_flip($this->ato);
            $this->ato = array_keys($newArray);
        } else {
            //There is no email addresses to process, so lets just leave.
            return false;
        }

        $this->CheckAddresses($this->ato);

        foreach ($this->ato as $to_address) {
            if (strpos($to_address, '<') !== false) {
                preg_match('/^.*<([^@]+\@[a-z0-9\._-]+)>/i', $to_address, $matches);
                if (isset($matches[1])) {
                    $to_address = $matches[1];
                }
            }
            $this->ClearAddresses();
            $this->AddAddress($to_address);
            $this->Send();
        }
        return true;
    }

    public function getHostName()
    {
        // Grab the server address, return a hostname for it.
        if ($host = gethostbyaddr($_SERVER['SERVER_ADDR'])) {
            return $host;
        } else {
            return '[' . $_SERVER['SERVER_ADDR'] . ']';
        }
    }

    /**
     * Queue mail to allow the queue manager to trigger
     * the email transfer.
     *
     * @access private
     */
    private function QueueMail()
    {
        $ec = new w2p_System_EventQueue();
        $vars = get_object_vars($this);
        return $ec->add(array('w2p_Utilities_Mail', 'SendQueuedMail'), $vars, 'w2p_Utilities_Mail', true);
    }

    /**
     * Dequeue the email and transfer it.  Called from the queue manager.
     *
     * @access public
     */
    public function SendQueuedMail($notUsed, $notUsed2, $notUsed3, $notUsed4, &$args)
    {

        foreach ($args as $key => $value) {
            $this->$key = $value;
        }

        ($this->transport == 'smtp') ? $this->IsSMTP() : $this->IsMail();

        return $this->Send();
    }

    /**
     *    Returns the whole e-mail , headers + message
     *
     *    can be used for displaying the message in plain text or logging it
     *
     *    @return string
     */
    public function Get()
    {
        $mail = $this->CreateHeader();
        $mail .= $this->CreateBody();
        return $mail;
    }

    /**
     *    check an email address validity
     *    @access public
     *    @param string $address : email address to check
     *    @return TRUE if email adress is ok
     */
    public function ValidEmail($address)
    {
        if (preg_match('/^(.*)\<(.+)\>$/D', $address, $regs)) {
            $address = $regs[2];
        }
        return (bool) preg_match('/^[^@ ]+@([-a-zA-Z0-9..]+)$/D', $address);
    }

    /**
     *    @deprecated
     */
    public function CheckAdresses($aad)
    {
        trigger_error("CheckAdresses() has been deprecated in v3.0 and will be removed by v4.0. Please use CheckAddresses() instead.", E_USER_NOTICE);
        return $this->CheckAddresses($aad);
    }

    /**
     *    check validity of email addresses
     *    @param    array $aad -
     *    @return if unvalid, output an error message and exit, this may -should- be customized
     */
    public function CheckAddresses($aad)
    {
        foreach ($aad as $ad) {
            if (!$this->ValidEmail($ad)) {
                return false;
            }
        }
        return true;
    }
}