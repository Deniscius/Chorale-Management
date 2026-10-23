<?php

namespace App\Services\Sms;

/** Explicit refusal before delivery; safe to retry after configuration is corrected. */
final class DeliveryRejected extends \RuntimeException {}
