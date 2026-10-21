{{--
    The platform's announcements for this tenant (Step 7.3), each dismissible. A dismissal is remembered in this browser only
    (a per-viewer convenience); an announcement comes back when its id changes.
--}}
@php($announcements = app(\Modules\Core\Contracts\Announcements::class)->active())
@if ($announcements !== [])
    <div data-announcements x-data="{ hidden: (() => { try { return JSON.parse(localStorage.getItem('dismissed-announcements') || '[]'); } catch (e) { return []; } })(),
        dismiss(id) { this.hidden.push(id); try { localStorage.setItem('dismissed-announcements', JSON.stringify(this.hidden)); } catch (e) {} } }">
        @foreach ($announcements as $announcement)
            <div class="alert alert-{{ $announcement->level }} rounded-0 mb-0 py-2 d-flex justify-content-between align-items-start gap-2" role="alert" x-show="!hidden.includes({{ $announcement->id }})" data-announcement="{{ $announcement->id }}">
                <span><strong>{{ $announcement->title }}</strong> {{ $announcement->body }}</span>
                <button type="button" class="btn-close" aria-label="{{ __('Dismiss') }}" @click="dismiss({{ $announcement->id }})"></button>
            </div>
        @endforeach
    </div>
@endif
