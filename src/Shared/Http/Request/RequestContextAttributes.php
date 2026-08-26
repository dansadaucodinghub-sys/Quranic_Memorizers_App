<?php

declare(strict_types=1);

namespace Qmdb\Shared\Http\Request;

final class RequestContextAttributes
{
    public const REQUEST_ID = 'qmdb.request_id';
    public const LOCALE = 'qmdb.locale';
    public const CSP_NONCE = 'qmdb.csp_nonce';

    private function __construct()
    {
    }
}
