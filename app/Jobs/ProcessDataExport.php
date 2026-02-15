<?php

namespace App\Jobs;

use App\Models\DataExportRequest;
use App\Models\User;
use App\Services\PrivacyComplianceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessDataExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 300;

    protected int $requestId;
    protected int $processorId;

    public function __construct(int $requestId, int $processorId)
    {
        $this->requestId = $requestId;
        $this->processorId = $processorId;
        $this->onQueue('exports');
    }

    public function handle(PrivacyComplianceService $service): void
    {
        $request = DataExportRequest::findOrFail($this->requestId);
        $processor = User::findOrFail($this->processorId);

        Log::info("Processing data export request", [
            'request_id' => $this->requestId,
            'user_id' => $request->user_id,
        ]);

        $service->processDataExportRequest($request, $processor);

        Log::info("Data export completed", [
            'request_id' => $this->requestId,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("Data export failed", [
            'request_id' => $this->requestId,
            'error' => $exception->getMessage(),
        ]);
    }
}
