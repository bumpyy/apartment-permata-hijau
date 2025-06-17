<?php

use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new
#[Layout('components.backend.layouts.app')]
class extends Component {}; ?>

<div><livewire:tenants-table/></div>
