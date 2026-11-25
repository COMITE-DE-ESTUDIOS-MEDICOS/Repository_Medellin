{{-- DOC-26 (UHC-01): la lista de Usuarios HC, todavía vacía. La tabla llega con UHC-06. --}}
@extends('layouts.app')
@section('titulo', 'Usuarios HC')

@section('contenido')

<div class="mb-4">
    <h1 class="text-lg font-semibold text-[var(--text)]">Usuarios HC</h1>
    <p class="text-sm text-[var(--muted)]">Las personas que atiende el componente.</p>
</div>

<div class="rounded-lg bg-[var(--card)] p-6 text-center text-sm text-[var(--muted)] ring-1 ring-[var(--border)]">
    Todavía no hay personas registradas.
</div>

@endsection
