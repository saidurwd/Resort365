<?php

namespace Modules\Billing\Services;

use Modules\Billing\Actions\PostCharge;
use Modules\Billing\Contracts\FolioPostingContract;
use Modules\Billing\DTOs\FolioCharge;
use Modules\Billing\DTOs\FolioPosting;
use Modules\Billing\Models\Folio;

/**
 * FolioPostingContract: PostCharge with the in-house check and the credit limit enforced.
 */
class FolioPostingService implements FolioPostingContract
{
    public function __construct(private readonly PostCharge $post) {}

    public function postCharge(FolioCharge $charge): FolioPosting
    {
        $line = $this->post->handle($charge, requireInHouse: true, enforceCreditLimit: true);
        $folio = Folio::query()->findOrFail($line->folio_id);

        return new FolioPosting($line->id, $folio->id, $folio->folio_no, $line->total, $folio->balance);
    }
}
