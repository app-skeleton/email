<?php defined('SYSPATH') or die('No direct script access.');
/*
 * Email message building and sending
 *
 * @package		Email
 * @author      Pap Tamas
 * @copyright   (c) 2011-2012 Pap Tamas
 * @website		https://github.com/paptamas/kohana-email
 * @license		http://www.opensource.org/licenses/isc-license.txt
 *
 */

class Kohana_Email {

	/**
	 * Swiftmailer instance
	 *
	 * @var object
	 */
	protected static $_mailer;

    /**
     * @var  object  Message instance
     */
    protected $_message;

    /**
     * Initialize a new Swift_Message, set the subject and body.
     *
     * @param string $subject
     * @param string $message
     * @param string $mime_type
     * @return Kohana_Email
     */
    protected function __construct($subject = NULL, $message = NULL, $mime_type)
    {
        // Create a new message, match internal character set
        $this->_message = Swift_Message::newInstance()
            ->setCharset(Kohana::$charset);

        if ($subject)
        {
            // Apply subject
            $this->subject($subject);
        }

        if ($message)
        {
            // Apply message, with mime type
            $this->message($message, $mime_type);
        }
    }

    /**
     * Creates a SwiftMailer instance.
     *
     * @param string $config_group
     * @return object Swift object
     */
	public static function mailer($config_group = 'default')
	{
		if ( ! Email::$_mailer)
		{
			// Load email configuration, make sure minimum defaults are set
			$config = Kohana::$config->load('email')->get($config_group);

			// Extract configured options

			if ($config['driver'] === 'smtp')
			{
				// Create SMTP transport
				$transport = Swift_SmtpTransport::newInstance($config['options']['hostname']);

				if (isset($config['options']['port']))
				{
					// Set custom port number
					$transport->setPort($config['options']['port']);
				}

				if (isset($config['options']['encryption']))
				{
					// Set encryption
					$transport->setEncryption($config['options']['encryption']);
				}

				if (isset($config['options']['username']))
				{
					// Require authentication, username
					$transport->setUsername($config['options']['username']);
				}

				if (isset($config['options']['password']))
				{
					// Require authentication, password
					$transport->setPassword($config['options']['password']);
				}

				if (isset($config['options']['timeout']))
				{
					// Use custom timeout setting
					$transport->setTimeout($config['options']['timeout']);
				}
			}
			elseif ($config['driver'] === 'sendmail')
			{
				// Create sendmail transport
				$transport = Swift_SendmailTransport::newInstance();

				if (isset($config['options']['command']))
				{
					// Use custom sendmail command
					$transport->setCommand($config['options']['command']);
				}
			}
			else
			{
				// Create native transport
				$transport = Swift_MailTransport::newInstance();

				if (isset($config['options']['params']))
				{
					// Set extra parameters for mail()
					$transport->setExtraParams($config['options']['params']);
				}
			}

			// Create the SwiftMailer instance
			Email::$_mailer = Swift_Mailer::newInstance($transport);
		}

		return Email::$_mailer;
	}

    /**
     * Set the message subject.
     *
     * @param $subject
     * @return  Email
     */
	public function subject($subject)
	{
		// Change the subject
		$this->_message->setSubject($subject);

		return $this;
	}

    /**
     * Set the message body.
     * Multiple bodies with different types can be added by calling this method multiple times.
     * Every email is required to have a "text/plain" message body.
     *
     * @param $body
     * @param null $mime_type
     * @return  Email
     */
	public function message($body, $mime_type = NULL)
	{
		if ( ! $mime_type OR $mime_type === 'text/plain')
		{
			// Set the main text/plain body
			$this->_message->setBody($body);
		}
		else
		{
			// Add a custom mime type
			$this->_message->addPart($body, $mime_type);
		}

		return $this;
	}

    /**
     * Add one or more email recipients..
     *
     *     // A single recipient
     *     $email->to('john.doe@domain.com', 'John Doe');
     *
     *     // Multiple entries
     *     $email->to(array(
     *         'frank.doe@domain.com',
     *         'jane.doe@domain.com' => 'Jane Doe',
     *     ));
     *
     * @param $email
     * @param null $name
     * @param string $type
     * @throws Email_Exception
     * @return  Email
     */
	public function to($email, $name = NULL, $type = 'to')
	{
		if (is_array($email))
		{
			foreach ($email as $key => $value)
			{
				if (ctype_digit((string) $key))
				{
					// Only an email address, no name
					$this->to($value, NULL, $type);
				}
				else
				{
					// Email address and name
					$this->to($key, $value, $type);
				}
			}
		}
		else
		{
			try
            {
                // Call $this->_message->{add$Type}($email, $name)
                call_user_func(array($this->_message, 'add'.ucfirst($type)), $email, $name);
            }
			catch(Exception $e)
            {
                throw new Email_Exception($e->getMessage(), array(), $e->getCode());
            }
		}

		return $this;
	}

    /**
     * Add a "carbon copy" email recipient.
     *
     * @param $email
     * @param null $name
     * @return  Email
     */
	public function cc($email, $name = NULL)
	{
		return $this->to($email, $name, 'cc');
	}

    /**
     * Add a "blind carbon copy" email recipient.
     *
     * @param $email
     * @param null $name
     * @return  Email
     */
	public function bcc($email, $name = NULL)
	{
		return $this->to($email, $name, 'bcc');
	}

    /**
     * Add email senders.
     *
     * @param $email
     * @param null $name
     * @param string $type
     * @return  Email
     */
	public function from($email, $name = NULL, $type = 'from')
	{
		// Call $this->_message->{add$Type}($email, $name)
		call_user_func(array($this->_message, 'add'.ucfirst($type)), $email, $name);

		return $this;
	}

    /**
     * Add "reply to" email sender.
     *
     * @param $email
     * @param null $name
     * @return  Email
     */
	public function reply_to($email, $name = NULL)
	{
		return $this->from($email, $name, 'replyto');
	}

    /**
     * Add actual email sender.
     *
     * [!!] This must be set when defining multiple "from" addresses!
     *
     * @param $email
     * @param null $name
     * @return  Email
     */
	public function sender($email, $name = NULL)
	{
		$this->_message->setSender($email, $name);
	}

    /**
     * Set the return path for bounce messages.
     *
     * @param $email
     * @return  Email
     */
	public function return_path($email)
	{
		$this->_message->setReplyPath($email);

		return $this;
	}

	/**
	 * Access the raw [Swiftmailer message](http://swiftmailer.org/docs/messages).
	 *
	 * @return  Swift_Message
	 */
	public function raw_message()
	{
		return $this->_message;
	}

    /**
     * Attach a file.
     *
     * @param $path
     * @return  Email
     */
	public function attach_file($path)
	{
		$this->_message->attach(Swift_Attachment::fromPath($path));

		return $this;
	}

    /**
     * Attach content to be sent as a file.
     *
     * @param $data
     * @param $file
     * @param null $mime
     * @return  Email
     */
	public function attach_content($data, $file, $mime = NULL)
	{
		if ( ! $mime)
		{
			// Get the mime type from the filename
			$mime = File::mime_by_ext(pathinfo($file, PATHINFO_EXTENSION));
		}

		$this->_message->attach(Swift_Attachment::newInstance($data, $file, $mime));

		return $this;
	}

    /**
     * Send the email.
     * Failed recipients can be collected by passing an array.
     *
     * @param string $config_group
     * @param array $failed
     * @throws Email_Exception
     */
	public function send($config_group = 'default', array & $failed = NULL)
	{
		try
        {
            return Email::mailer($config_group)->send($this->_message, $failed);
        }
        catch (Exception $e)
        {
            throw new Email_Exception($e->getMessage(), array(), $e->getCode());
        }
	}

    /**
     * Create a new email message.
     *
     * @param string $subject
     * @param string $message
     * @param string $mime_type
     * @return  Email
     */
    public static function factory($subject = NULL, $message = NULL, $mime_type = NULL)
    {
        // Load swiftmailer if not already loaded
        if ( ! defined('SWIFT_REQUIRED_LOADED'))
        {
            require Kohana::find_file('vendor/Swiftmailer', 'lib/swift_required');
        }

        return new Email($subject, $message, $mime_type);
    }

} // End Kohana_Email