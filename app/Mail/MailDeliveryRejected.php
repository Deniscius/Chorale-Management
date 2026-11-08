<?php

namespace App\Mail;

use Symfony\Component\Mailer\Exception\TransportException;

/** A definite rejection before the provider accepted an e-mail. */
final class MailDeliveryRejected extends TransportException {}
