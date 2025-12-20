<div class="space-y-6">
    <!-- Backup Header -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">{{ $record->name }}</h3>
                <p class="text-sm text-gray-500 mt-1">Created {{ $record->created_at->format('F j, Y \a\t g:i A') }}</p>
            </div>
            <div class="flex items-center space-x-2">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-{{ $record->type_color }}-100 text-{{ $record->type_color }}-800">
                    {{ $record->type_label }}
                </span>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-{{ $record->status_color }}-100 text-{{ $record->status_color }}-800">
                    {{ $record->status_label }}
                </span>
            </div>
        </div>
        
        @if($record->description)
            <p class="mt-4 text-sm text-gray-600">{{ $record->description }}</p>
        @endif
    </div>

    <!-- Backup Details -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- General Information -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <h4 class="text-base font-medium text-gray-900 mb-4">General Information</h4>
            <dl class="space-y-3">
                <div class="flex justify-between">
                    <dt class="text-sm font-medium text-gray-500">Type:</dt>
                    <dd class="text-sm text-gray-900">{{ $record->type_label }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-sm font-medium text-gray-500">Format:</dt>
                    <dd class="text-sm text-gray-900">{{ $record->format_label }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-sm font-medium text-gray-500">Status:</dt>
                    <dd class="text-sm text-gray-900">{{ $record->status_label }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-sm font-medium text-gray-500">File Size:</dt>
                    <dd class="text-sm text-gray-900">{{ $record->formatted_file_size }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-sm font-medium text-gray-500">Duration:</dt>
                    <dd class="text-sm text-gray-900">{{ $record->duration ?? 'N/A' }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-sm font-medium text-gray-500">Progress:</dt>
                    <dd class="text-sm text-gray-900">
                        @if($record->isProcessing())
                            {{ number_format($record->progress_percentage, 1) }}%
                        @else
                            N/A
                        @endif
                    </dd>
                </div>
            </dl>
        </div>

        <!-- Processing Information -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <h4 class="text-base font-medium text-gray-900 mb-4">Processing Information</h4>
            <dl class="space-y-3">
                <div class="flex justify-between">
                    <dt class="text-sm font-medium text-gray-500">Total Records:</dt>
                    <dd class="text-sm text-gray-900">{{ number_format($record->total_records) }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-sm font-medium text-gray-500">Processed Records:</dt>
                    <dd class="text-sm text-gray-900">{{ number_format($record->processed_records) }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-sm font-medium text-gray-500">Failed Records:</dt>
                    <dd class="text-sm text-gray-900">{{ number_format($record->failed_records) }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-sm font-medium text-gray-500">Started At:</dt>
                    <dd class="text-sm text-gray-900">{{ $record->started_at?->format('M j, Y g:i A') ?? 'N/A' }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-sm font-medium text-gray-500">Completed At:</dt>
                    <dd class="text-sm text-gray-900">{{ $record->completed_at?->format('M j, Y g:i A') ?? 'N/A' }}</dd>
                </div>
            </dl>
        </div>
    </div>

    <!-- Tables Information -->
    @if($record->tables && count($record->tables) > 0)
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <h4 class="text-base font-medium text-gray-900 mb-4">Tables Included</h4>
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-2">
                @foreach($record->tables as $table)
                    <span class="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium bg-gray-100 text-gray-800">
                        {{ $table }}
                    </span>
                @endforeach
            </div>
        </div>
    @endif

    <!-- User and Store Information -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <h4 class="text-base font-medium text-gray-900 mb-4">User Information</h4>
            <dl class="space-y-3">
                <div class="flex justify-between">
                    <dt class="text-sm font-medium text-gray-500">Created By:</dt>
                    <dd class="text-sm text-gray-900">{{ $record->user?->name ?? 'System' }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-sm font-medium text-gray-500">User Email:</dt>
                    <dd class="text-sm text-gray-900">{{ $record->user?->email ?? 'N/A' }}</dd>
                </div>
            </dl>
        </div>

        @if(config('backup.multi_store.enabled', true) && $record->store)
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h4 class="text-base font-medium text-gray-900 mb-4">Store Information</h4>
                <dl class="space-y-3">
                    <div class="flex justify-between">
                        <dt class="text-sm font-medium text-gray-500">Store Name:</dt>
                        <dd class="text-sm text-gray-900">{{ $record->store->name }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-sm font-medium text-gray-500">Store Code:</dt>
                        <dd class="text-sm text-gray-900">{{ $record->store->code ?? 'N/A' }}</dd>
                    </div>
                </dl>
            </div>
        @endif
    </div>

    <!-- Progress Bar (for processing backups) -->
    @if($record->isProcessing())
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <h4 class="text-base font-medium text-gray-900 mb-4">Backup Progress</h4>
            <div class="w-full bg-gray-200 rounded-full h-2.5">
                <div class="bg-blue-600 h-2.5 rounded-full transition-all duration-300" style="width: {{ $record->progress_percentage }}%"></div>
            </div>
            <p class="mt-2 text-sm text-gray-600">
                {{ number_format($record->processed_records) }} of {{ number_format($record->total_records) }} records processed
                ({{ number_format($record->progress_percentage, 1) }}%)
            </p>
        </div>
    @endif

    <!-- Errors (for failed backups) -->
    @if($record->isFailed() && $record->errors && count($record->errors) > 0)
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <h4 class="text-base font-medium text-gray-900 mb-4">Error Details</h4>
            <div class="space-y-2">
                @foreach($record->errors as $error)
                    <div class="p-3 bg-red-50 border border-red-200 rounded-md">
                        <p class="text-sm text-red-800">{{ $error }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <!-- File Information -->
    @if($record->isCompleted() && $record->file_path)
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <h4 class="text-base font-medium text-gray-900 mb-4">File Information</h4>
            <dl class="space-y-3">
                <div class="flex justify-between">
                    <dt class="text-sm font-medium text-gray-500">File Path:</dt>
                    <dd class="text-sm text-gray-900 font-mono">{{ $record->file_path }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-sm font-medium text-gray-500">Storage Disk:</dt>
                    <dd class="text-sm text-gray-900">{{ $record->disk }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-sm font-medium text-gray-500">File Exists:</dt>
                    <dd class="text-sm text-gray-900">
                        @if($record->fileExists())
                            <span class="text-green-600">Yes</span>
                        @else
                            <span class="text-red-600">No</span>
                        @endif
                    </dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-sm font-medium text-gray-500">Download URL:</dt>
                    <dd class="text-sm text-gray-900">
                        @if($record->download_url)
                            <a href="{{ $record->download_url }}" class="text-blue-600 hover:text-blue-800" target="_blank">
                                Download File
                            </a>
                        @else
                            N/A
                        @endif
                    </dd>
                </div>
            </dl>
        </div>
    @endif
</div>