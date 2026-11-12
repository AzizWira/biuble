<section id="admin" class="panel"><h2>Akun admin toko</h2>
@foreach($admins as $admin)
    <article class="managed-account">
        <strong>{{ $admin->name }}</strong><p>{{ $admin->email }}</p>
        @include('partials.account-status', ['managedUser' => $admin])
        @include('partials.account-management', ['managedUser' => $admin])
    </article>
@endforeach
<form class="stack" action="{{ route('staff.admin') }}" method="post">@csrf
<label class="field">Nama admin<input name="name" maxlength="120" required></label>
<label class="field">Email admin baru<input name="email" type="email" maxlength="254" required></label>
<button class="button">Siapkan akun admin</button>
</form></section>
