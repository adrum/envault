<?php

namespace App\Http\Controllers\Settings;

use App\Models\App;
use Inertia\Inertia;
use Inertia\Response;
use App\Models\Webhook;
use App\Models\Environment;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\EnvironmentType;
use App\Models\WebhookDelivery;
use Illuminate\Validation\Rule;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use App\Support\Webhooks\WebhookEvents;
use App\Support\Webhooks\WebhookDispatcher;

class WebhookController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', Webhook::class);

        $webhooks = Webhook::with(['subscriptions.subscribable', 'creator'])
            ->latest()
            ->get()
            ->map(fn (Webhook $w) => $this->presentWebhook($w));

        $deliveries = WebhookDelivery::with('webhook:id,name')
            ->latest()
            ->limit(100)
            ->get()
            ->map(fn (WebhookDelivery $d) => [
                'id' => $d->id,
                'webhook_id' => $d->webhook_id,
                'webhook_name' => $d->webhook?->name,
                'event' => $d->event,
                'url' => $d->url,
                'response_status' => $d->response_status,
                'error' => $d->error,
                'attempt' => $d->attempt,
                'delivered_at' => $d->delivered_at?->toIso8601String(),
                'created_at' => $d->created_at?->toIso8601String(),
                'success' => $d->isSuccess(),
            ]);

        return Inertia::render('settings/webhooks', [
            'webhooks' => $webhooks,
            'deliveries' => $deliveries,
            'available_events' => WebhookEvents::all(),
            'scope_options' => $this->scopeOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Webhook::class);

        $validated = $this->validatePayload($request);

        $webhook = Webhook::create([
            'name' => $validated['name'],
            'url' => $validated['url'],
            'events' => $validated['events'],
            'active' => $validated['active'] ?? true,
            'all_apps' => $validated['all_apps'] ?? false,
            'secret' => Str::random(40),
            'created_by' => $request->user()->id,
        ]);

        $this->syncSubscriptions($webhook, $validated['subscriptions'] ?? []);

        toastSuccess("Webhook \"{$webhook->name}\" created.");

        return redirect()->route('webhooks.index');
    }

    public function update(Request $request, Webhook $webhook): RedirectResponse
    {
        $this->authorize('update', $webhook);

        $validated = $this->validatePayload($request);

        $webhook->update([
            'name' => $validated['name'],
            'url' => $validated['url'],
            'events' => $validated['events'],
            'active' => $validated['active'] ?? $webhook->active,
            'all_apps' => $validated['all_apps'] ?? false,
        ]);

        $this->syncSubscriptions($webhook, $validated['subscriptions'] ?? []);

        toastSuccess("Webhook \"{$webhook->name}\" updated.");

        return redirect()->route('webhooks.index');
    }

    public function destroy(Webhook $webhook): RedirectResponse
    {
        $this->authorize('delete', $webhook);

        $name = $webhook->name;
        $webhook->delete();

        toastSuccess("Webhook \"{$name}\" deleted.");

        return redirect()->route('webhooks.index');
    }

    public function test(Webhook $webhook, Request $request, WebhookDispatcher $dispatcher): RedirectResponse
    {
        $this->authorize('update', $webhook);

        $dispatcher->dispatchTest($webhook, $request->user());

        toastInfo('Test event queued.');

        return back();
    }

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'url' => ['required', 'url', 'max:2048'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['string', 'in:' . implode(',', WebhookEvents::all())],
            'active' => ['nullable', 'boolean'],
            'all_apps' => ['nullable', 'boolean'],
            'subscriptions' => [
                Rule::requiredIf(fn () => !$request->boolean('all_apps')),
                'array',
            ],
            'subscriptions.*.type' => ['required', 'in:app,environment,environment_type'],
            'subscriptions.*.id' => ['required', 'integer'],
        ]);
    }

    private function syncSubscriptions(Webhook $webhook, array $subscriptions): void
    {
        $typeMap = [
            'app' => App::class,
            'environment' => Environment::class,
            'environment_type' => EnvironmentType::class,
        ];

        $webhook->subscriptions()->delete();

        foreach ($subscriptions as $sub) {
            $webhook->subscriptions()->create([
                'subscribable_type' => $typeMap[$sub['type']],
                'subscribable_id' => $sub['id'],
            ]);
        }
    }

    private function presentWebhook(Webhook $webhook): array
    {
        return [
            'id' => $webhook->id,
            'name' => $webhook->name,
            'url' => $webhook->url,
            'events' => $webhook->events,
            'active' => $webhook->active,
            'all_apps' => $webhook->all_apps,
            'created_at' => $webhook->created_at?->toIso8601String(),
            'subscriptions' => $webhook->subscriptions->map(fn ($s) => [
                'type' => match ($s->subscribable_type) {
                    App::class => 'app',
                    Environment::class => 'environment',
                    EnvironmentType::class => 'environment_type',
                    default => 'unknown',
                },
                'id' => $s->subscribable_id,
                'label' => $this->subscriptionLabel($s->subscribable_type, $s->subscribable),
            ])->values(),
        ];
    }

    private function subscriptionLabel(string $type, $model): string
    {
        if (!$model) {
            return '(deleted)';
        }

        return match ($type) {
            App::class => $model->name,
            EnvironmentType::class => $model->name,
            Environment::class => ($model->app->name ?? '?') . ' / ' . $model->label,
            default => 'unknown',
        };
    }

    private function scopeOptions(): array
    {
        return [
            'apps' => App::with(['environments.environmentType'])
                ->orderBy('name')
                ->get()
                ->map(fn (App $a) => [
                    'id' => $a->id,
                    'name' => $a->name,
                    'environments' => $a->environments->map(fn (Environment $e) => [
                        'id' => $e->id,
                        'label' => $e->label,
                    ])->values(),
                ])->values(),
            'environment_types' => EnvironmentType::orderBy('sort_order')
                ->get(['id', 'name', 'color'])
                ->values(),
        ];
    }
}
