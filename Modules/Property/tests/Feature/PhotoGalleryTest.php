<?php

/*
| Photo galleries (HasPhotos) on cottage types, room types and cottages, through Core's
| authorized attachment routes.
*/

use App\Support\Attachments\Attachments;
use App\Support\Authorization\DefaultRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Models\Media;
use Modules\Property\Models\Cottage;
use Modules\Property\Models\CottageType;
use Modules\Property\Tests\Support\PropertySetup;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\withSession;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('attachments');
    PropertySetup::tenant();
});

function villaType(): CottageType
{
    $propertyId = PropertySetup::property('CXB')->id;

    return PropertySetup::run(fn (): CottageType => CottageType::factory()->create(['property_id' => $propertyId, 'name' => 'Family Villa']));
}

it('adds a photo with a thumbnail and shows it in the gallery', function (): void {
    $type = villaType();

    actingAs(PropertySetup::user(DefaultRole::GeneralManager, 'CXB'));
    withSession(PropertySetup::current('CXB'));

    post(tenantUrl('sunrise', '/core/attachments/cottage_type/'.$type->id), [
        'collection' => Attachments::PHOTOS, 'file' => UploadedFile::fake()->image('villa-front.jpg', 1200, 800),
    ])->assertSessionHasNoErrors()->assertSessionHas('success');

    $photo = PropertySetup::run(fn (): Media => Media::query()->sole());
    expect($photo->collection_name)->toBe(Attachments::PHOTOS)
        ->and($photo->hasGeneratedConversion(Attachments::THUMB))->toBeTrue();

    get(tenantUrl('sunrise', '/property/cottage-types/'.$type->id.'/edit'))->assertOk()->assertSee('villa-front.jpg')->assertSeeHtml('conversion=thumb');
    get(tenantUrl('sunrise', '/core/attachments/'.$photo->id.'?conversion=thumb'))->assertOk();
    get(tenantUrl('sunrise', '/core/attachments/'.$photo->id))->assertOk();

    delete(tenantUrl('sunrise', '/core/attachments/'.$photo->id))->assertSessionHas('success');
    expect(PropertySetup::run(fn (): int => Media::query()->count()))->toBe(0);
});

it('accepts only images in a gallery, and galleries only where the record has one', function (): void {
    $type = villaType();

    actingAs(PropertySetup::user(DefaultRole::TenantOwner));

    post(tenantUrl('sunrise', '/core/attachments/cottage_type/'.$type->id), [
        'collection' => Attachments::PHOTOS, 'file' => UploadedFile::fake()->create('price-list.pdf', 20, 'application/pdf'),
    ])->assertSessionHasErrors('file');
    post(tenantUrl('sunrise', '/core/attachments/cottage_type/'.$type->id), [
        'collection' => 'secret', 'file' => UploadedFile::fake()->image('a.jpg'),
    ])->assertSessionHasErrors('collection');

    // Properties have attachments and a logo, but no gallery.
    post(tenantUrl('sunrise', '/core/attachments/property/'.PropertySetup::property('CXB')->id), [
        'collection' => Attachments::PHOTOS, 'file' => UploadedFile::fake()->image('a.jpg'),
    ])->assertNotFound();

    expect(PropertySetup::run(fn (): int => Media::query()->count()))->toBe(0);
});

it('lets viewers see photos but only managers add them', function (): void {
    $type = villaType();
    $cottage = PropertySetup::run(fn (): Cottage => Cottage::factory()->create(['property_id' => $type->property_id, 'cottage_type_id' => $type->id]));

    actingAs(PropertySetup::user(DefaultRole::FrontDeskAgent, 'CXB'));
    withSession(PropertySetup::current('CXB'));

    post(tenantUrl('sunrise', '/core/attachments/cottage/'.$cottage->id), [
        'collection' => Attachments::PHOTOS, 'file' => UploadedFile::fake()->image('a.jpg'),
    ])->assertForbidden();
    get(tenantUrl('sunrise', '/property/cottages/'.$cottage->id))->assertOk()->assertSee(__('No photos yet'))->assertDontSee(__('Add photo'));
});
