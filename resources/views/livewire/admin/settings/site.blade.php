<section class="w-full">
    @include('partials.settings-heading')

    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="space-y-8">
            <!-- Success Message -->
            @if (session('status'))
                <div class="rounded-lg bg-green-50 border border-green-200 p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-green-800">{{ session('status') }}</p>
                        </div>
                    </div>
                </div>
            @endif

            <form wire:submit="updateSiteSettings" class="space-y-8">
                <!-- Site Information -->
                <div class="bg-white shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                            <svg class="inline-block w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            Site Information
                        </h3>
                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                            <flux:input wire:model="site_name" label="Site Name" type="text" required />
                            <flux:input wire:model="site_description" label="Site Description" type="text" />
                            <flux:input wire:model="contact_email" label="Contact Email" type="email" required />
                            <flux:input wire:model="contact_phone" label="Contact Phone" type="text" />
                            <flux:input wire:model="site_url" label="Site URL" type="url" required />
                        </div>
                    </div>
                </div>

                <!-- Real-time Polling Settings -->
                <div class="bg-white shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                            <svg class="inline-block w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                            </svg>
                            Real-time Polling Settings
                        </h3>
                        <div class="space-y-4">
                            <flux:checkbox wire:model="enable_realtime_polling" label="Enable Real-time Polling" />

                            @if($enable_realtime_polling)
                                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                                    <flux:input wire:model="polling_interval_active" label="Active User Interval (seconds)" type="number" min="10" max="300" />
                                    <flux:input wire:model="polling_interval_inactive" label="Inactive User Interval (seconds)" type="number" min="30" max="600" />
                                    <flux:input wire:model="polling_interval_mobile" label="Mobile Device Interval (seconds)" type="number" min="15" max="300" />
                                    <flux:input wire:model="inactivity_timeout" label="Inactivity Timeout (seconds)" type="number" min="60" max="1800" />
                                </div>

                                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                                    <div class="flex">
                                        <div class="flex-shrink-0">
                                            <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                                            </svg>
                                        </div>
                                        <div class="ml-3">
                                            <h4 class="text-sm font-medium text-blue-800">Polling Configuration</h4>
                                            <div class="mt-2 text-sm text-blue-700">
                                                <p><strong>Active Users:</strong> {{ $polling_interval_active }} seconds</p>
                                                <p><strong>Inactive Users:</strong> {{ $polling_interval_inactive }} seconds</p>
                                                <p><strong>Mobile Devices:</strong> {{ $polling_interval_mobile }} seconds</p>
                                                <p><strong>Inactivity Timeout:</strong> {{ $inactivity_timeout }} seconds</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @else
                                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                                    <div class="flex">
                                        <div class="flex-shrink-0">
                                            <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                            </svg>
                                        </div>
                                        <div class="ml-3">
                                            <p class="text-sm text-yellow-700">
                                                Real-time polling is disabled. Users will need to manually refresh to see booking updates.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Booking System Settings -->
                <div class="bg-white shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                            <svg class="inline-block w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                            Booking System Settings
                        </h3>
                        <div class="space-y-4">
                            <flux:checkbox wire:model="enable_booking_system" label="Enable Booking System" />

                            @if($enable_booking_system)
                                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                                    <flux:input wire:model="max_bookings_per_tenant" label="Max Bookings per Tenant" type="number" min="1" max="10" />
                                    <flux:input wire:model="booking_advance_days" label="Booking Advance Days" type="number" min="1" max="365" />
                                    <flux:checkbox wire:model="allow_booking_cancellations" label="Allow Booking Cancellations" />
                                    <flux:input wire:model="cancellation_hours_limit" label="Cancellation Hours Limit" type="number" min="1" max="168" />
                                    <flux:checkbox wire:model="enable_cross_court_conflict_detection" label="Enable Cross-Court Conflict Detection" />
                                </div>

                                @if($enable_cross_court_conflict_detection)
                                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                                        <div class="flex">
                                            <div class="flex-shrink-0">
                                                <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                                                </svg>
                                            </div>
                                            <div class="ml-3">
                                                <h4 class="text-sm font-medium text-blue-800">Cross-Court Conflict Detection</h4>
                                                <div class="mt-2 text-sm text-blue-700">
                                                    <p>Prevents tenants from booking multiple courts at the same time.</p>
                                                    <p>This helps ensure tenants can only use one court at a time since they must be physically present.</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Performance Settings -->
                <div class="bg-white shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                            <svg class="inline-block w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                            </svg>
                            Performance Settings
                        </h3>
                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                            <flux:checkbox wire:model="enable_caching" label="Enable Caching" />
                            <flux:input wire:model="cache_duration" label="Cache Duration (seconds)" type="number" min="60" max="3600" />
                            <flux:checkbox wire:model="enable_compression" label="Enable Compression" />
                            <flux:checkbox wire:model="enable_minification" label="Enable Minification" />
                        </div>
                    </div>
                </div>

                <!-- Security Settings -->
                <div class="bg-white shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                            <svg class="inline-block w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                            </svg>
                            Security Settings
                        </h3>
                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                            <flux:checkbox wire:model="enable_rate_limiting" label="Enable Rate Limiting" />
                            <flux:input wire:model="rate_limit_requests" label="Rate Limit Requests" type="number" min="10" max="1000" />
                            <flux:input wire:model="rate_limit_minutes" label="Rate Limit Minutes" type="number" min="1" max="60" />
                            <flux:checkbox wire:model="enable_csrf_protection" label="Enable CSRF Protection" />
                            <flux:checkbox wire:model="enable_xss_protection" label="Enable XSS Protection" />
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex justify-between">
                    <flux:button type="button" wire:click="resetToDefaults">
                        Reset to Defaults
                    </flux:button>
                    <flux:button type="submit" variant="primary">
                        Save Settings
                    </flux:button>
                </div>
            </form>
        </div>
    </div>
</section>
