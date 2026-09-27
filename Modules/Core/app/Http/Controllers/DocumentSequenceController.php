<?php

namespace Modules\Core\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use InvalidArgumentException;
use Modules\Core\Actions\SaveDocumentSequence;
use Modules\Core\Contracts\DocumentNumbers;
use Modules\Core\DTOs\DocumentType;
use Modules\Core\Enums\SequenceReset;
use Modules\Core\Http\Requests\SaveDocumentSequenceRequest;
use Modules\Core\Models\DocumentSequence;

/**
 * Document numbering per type (tenant level). TODO(step-0.8): per-property sequences.
 */
class DocumentSequenceController extends Controller
{
    public function __construct(private readonly DocumentNumbers $numbers) {}

    public function index(): View
    {
        $sequences = DocumentSequence::query()->whereNull('property_id')->get()->keyBy('document_type');

        $rows = collect($this->numbers->types())->map(function (DocumentType $type) use ($sequences): array {
            $sequence = $sequences->get($type->key);
            $format = $sequence->format ?? $type->format;
            $prefix = $sequence->prefix ?? $type->prefix;
            $reset = $sequence->reset ?? $type->reset;
            $restarts = $reset === SequenceReset::Yearly && $sequence?->period !== null && $sequence->period !== (int) now()->format('Y');
            $next = $sequence === null || $restarts ? 1 : $sequence->next_number;

            return [
                'type' => $type,
                'format' => $format,
                'reset' => $reset,
                'preview' => $this->numbers->format($format, $prefix, $next, now()),
            ];
        })->values()->all();

        return view('core::sequences.index', ['rows' => $rows]);
    }

    public function edit(string $type): View
    {
        $definition = $this->typeOr404($type);
        $sequence = DocumentSequence::query()->where('document_type', $type)->whereNull('property_id')->first();

        return view('core::sequences.edit', [
            'type' => $definition,
            'prefix' => $sequence->prefix ?? $definition->prefix,
            'format' => $sequence->format ?? $definition->format,
            'nextNumber' => $sequence->next_number ?? 1,
            'reset' => $sequence->reset ?? $definition->reset,
        ]);
    }

    public function update(SaveDocumentSequenceRequest $request, string $type, SaveDocumentSequence $save): RedirectResponse
    {
        $this->typeOr404($type);

        $sequence = $save->handle(
            $type,
            (string) $request->string('prefix'),
            (string) $request->string('format'),
            $request->integer('next_number'),
            SequenceReset::from((string) $request->string('reset')),
        );

        return to_route('core.sequences.index')->with('success', __('Numbering saved. Next number: :number', [
            'number' => $this->numbers->format($sequence->format, $sequence->prefix, $sequence->next_number, now()),
        ]));
    }

    private function typeOr404(string $type): DocumentType
    {
        try {
            return $this->numbers->type($type);
        } catch (InvalidArgumentException) {
            abort(404);
        }
    }
}
