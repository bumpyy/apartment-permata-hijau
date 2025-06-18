@props(['value'])

<div>
    <a href="{{ route('admin.tenant.show', $value) }}">Tenant detail</a>
</div>
