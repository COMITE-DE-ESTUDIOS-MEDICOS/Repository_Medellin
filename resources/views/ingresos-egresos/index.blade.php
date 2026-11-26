{{-- DOC-27 (IYE-01): la pantalla de Ingresos y egresos, todavía vacía. La tabla llega con IYE-05. --}}
@extends('layouts.app')
@section('titulo', 'Ingresos y egresos')

@section('contenido')

<div class="mb-4">
    <h1 class="text-lg font-semibold text-[var(--text)]">Ingresos y egresos</h1>
    <p class="text-sm text-[var(--muted)]">Quién está dentro de cada sede, desde cuándo y cuándo salió.</p>
</div>

<div class="rounded-lg bg-[var(--card)] p-6 text-center text-sm text-[var(--muted)] ring-1 ring-[var(--border)]">
    Todavía no hay ingresos registrados.
</div>

@endsection
