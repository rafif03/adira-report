<h1 class="text-2xl font-semibold">Guest Dashboard</h1>
<p class="mt-2 text-sm text-zinc-600">
	Halo, {{ auth()->user()->name }}. Akun Anda saat ini masih berstatus <strong>guest</strong>.
</p>

<div class="mt-4 rounded border border-zinc-200 bg-white p-4 text-sm text-zinc-700 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200">
	<p class="font-semibold">Akses belum ditentukan</p>
	<p class="mt-1">
		Anda belum bisa melihat tabel dashboard maupun melakukan input laporan.
		Silakan hubungi admin agar ditentukan <strong>area</strong> dan <strong>hak akses</strong> Anda.
	</p>
</div>
