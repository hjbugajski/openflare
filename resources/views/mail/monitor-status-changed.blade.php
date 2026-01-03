<x-mail::message>
@if($status === \App\MonitorStatus::Down)
# 🔴 {{ $monitor->name }} is down

Your monitor detected an issue.
@else
# 🟢 {{ $monitor->name }} is up

Your monitor has recovered.
@endif

**URL:** {{ $monitor->url }}

**Checked at:** {{ $check->checked_at->format('M j, Y g:i A T') }}

@if($check->status_code)
**Status code:** {{ $check->status_code }}
@endif

@if($check->response_time_ms)
**Response time:** {{ $check->response_time_ms }}ms
@endif

@if($status === \App\MonitorStatus::Down && $check->error_message)
**Error:** {{ $check->error_message }}
@endif
</x-mail::message>
