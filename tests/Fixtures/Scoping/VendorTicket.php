<?php

declare(strict_types=1);

namespace RoundlyConsulting\Teams\Tests\Fixtures\Scoping;

use Illuminate\Database\Eloquent\Model;

/**
 * Stands in for another package's model — it knows nothing about team scoping.
 */
class VendorTicket extends Model
{
    /** @var string */
    protected $table = 'vendor_tickets';

    /** @var array<string> */
    protected $guarded = [];
}
