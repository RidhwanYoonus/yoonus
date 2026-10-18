@php($pending = \App\Support\PendingMigrations::list())
@php($result = session('db_update'))
@if ($result)
    <div class="px-4 py-2 text-center text-sm font-medium text-white" style="background: {{ $result['ok'] ? '#16a34a' : '#dc2626' }}">{{ $result['message'] }}</div>
@endif
@if ($pending && auth()->user()?->is_admin)
    <form method="POST" action="{{ route('admin.update-database') }}" class="flex flex-wrap items-center justify-center gap-3 px-4 py-2 text-center text-sm font-medium text-white" style="background: #d97706">
        @csrf
        <span>⚠ Your last update needs a database update ({{ count($pending) }} pending). Until then, saving products and checkout may not work.</span>
        <button type="submit" class="rounded-md bg-white px-3 py-1 font-semibold" style="color: #b45309">Update database now</button>
    </form>
@endif
