<?php

namespace Modules\Core\Http\Controllers;

use App\Support\Tenancy\PropertyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
 * Document numbering per type, company-wide or per property (?property=ID, the user's properties only).
 */
class DocumentSequenceController extends Controller
{
    public function __construct(
        private readonly DocumentNumbers $numbers,
        private readonly PropertyContext $properties,
    ) {}

    public function index(Request $request): View
    {
        $propertyId = $this->propertyFrom($request);
        $sequences = DocumentSequence::query()->where('property_id', $propertyId)->get()->keyBy('document_type');

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

        return view('core::sequences.index', ['rows' => $rows, 'propertyId' => $propertyId, 'properties' => $this->properties->accessible()]);
    }

    public function edit(Request $request, string $type): View
    {
        $definition = $this->typeOr404($type);
        $propertyId = $this->propertyFrom($request);
        $sequence = DocumentSequence::query()->where('document_type', $type)->where('property_id', $propertyId)->first();

        return view('core::sequences.edit', [
            'type' => $definition,
            'propertyId' => $propertyId,
            'propertyName' => $propertyId !== null ? $this->properties->accessible()[$propertyId] : null,
            'prefix' => $sequence->prefix ?? $definition->prefix,
            'format' => $sequence->format ?? $definition->format,
            'nextNumber' => $sequence->next_number ?? 1,
            'reset' => $sequence->reset ?? $definition->reset,
        ]);
    }

    public function update(SaveDocumentSequenceRequest $request, string $type, SaveDocumentSequence $save): RedirectResponse
    {
        $this->typeOr404($type);
        $propertyId = $this->propertyFrom($request);

        $sequence = $save->handle(
            $type,
            (string) $request->string('prefix'),
            (string) $request->string('format'),
            $request->integer('next_number'),
            SequenceReset::from((string) $request->string('reset')),
            $propertyId,
        );

        return to_route('core.sequences.index', array_filter(['property' => $propertyId]))->with('success', __('Numbering saved. Next number: :number', [
            'number' => $this->numbers->format($sequence->format, $sequence->prefix, $sequence->next_number, now()),
        ]));
    }

    /**
     * The property from ?property=, only when the user may access it.
     */
    private function propertyFrom(Request $request): ?int
    {
        $property = $request->input('property');

        if ($property === null || $property === '') {
            return null;
        }

        abort_unless(is_numeric($property) && array_key_exists((int) $property, $this->properties->accessible()), 404);

        return (int) $property;
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
