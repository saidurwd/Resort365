<?php

namespace Modules\Reservation\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Reservation\Actions\SaveQuote;
use Modules\Reservation\Http\Requests\Api\QuoteRequest;
use Modules\Reservation\Http\Resources\BookingQuoteResource;
use Modules\Reservation\Http\Resources\QuoteResource;
use Modules\Reservation\Models\Quote;
use Modules\Reservation\Services\BookingQuoter;

/**
 * Quotes (Step 8.1).
 *
 * @tags Reservation
 */
class QuoteController extends Controller
{
    /**
     * Price a stay.
     *
     * Prices the rooms and cottages for the dates, with the deposit due. With `save: true` the quote is kept for the guest
     * (numbered, valid for the property's quote days) and answered with 201; nothing is held either way.
     */
    public function store(QuoteRequest $request, BookingQuoter $quoter, SaveQuote $save): JsonResponse
    {
        $userId = (int) $request->user()?->getAuthIdentifier();

        if ($request->boolean('save')) {
            $quote = $save->handle($request->reservation($request->integer('guest_id'), $userId));

            return new QuoteResource($quote->load('items'))->response()->setStatusCode(201);
        }

        return new BookingQuoteResource($quoter->quote($request->reservation(0, $userId)))->response();
    }

    /**
     * Show a saved quote.
     */
    public function show(Request $request, Quote $quote): QuoteResource
    {
        return new QuoteResource($quote->load('items'));
    }
}
