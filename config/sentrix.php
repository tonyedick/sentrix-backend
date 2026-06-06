<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Escalation chain
    |--------------------------------------------------------------------------
    |
    | Toggles for the automatic safety-escalation pipeline. Both are on by
    | default; either can be disabled per environment.
    |
    |  - auto_emergency_on_overdue_trip: when a trip is flagged overdue, raise an
    |    emergency for the monitored user (idempotent — one live emergency per trip).
    |  - auto_incident_for_critical_emergency: when an emergency is triggered at
    |    `critical` severity, open a linked incident (idempotent per emergency).
    |
    */

    'escalation' => [
        'auto_emergency_on_overdue_trip' => (bool) env('SENTRIX_AUTO_EMERGENCY_ON_OVERDUE', true),
        'auto_incident_for_critical_emergency' => (bool) env('SENTRIX_AUTO_INCIDENT_FOR_CRITICAL', true),
        'auto_emergency_on_lost_contact' => (bool) env('SENTRIX_AUTO_EMERGENCY_ON_LOST_CONTACT', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Operational notifications
    |--------------------------------------------------------------------------
    |
    | Channels used to notify responders of operational events. Only the
    | channels implemented by the notification classes take effect
    | (`mail`, `database`, `broadcast`); listing an unimplemented channel here is
    | ignored until a corresponding channel + `to{Channel}()` method is added
    | (e.g. SMS via Vonage, push via FCM).
    |
    */

    'notifications' => [
        'channels' => array_values(array_filter(
            explode(',', (string) env('SENTRIX_NOTIFICATION_CHANNELS', 'mail,database,broadcast'))
        )),
    ],

    /*
    |--------------------------------------------------------------------------
    | Location tracking
    |--------------------------------------------------------------------------
    |
    | broadcast_coalesce_seconds: live position updates are coalesced to at most
    | one broadcast per trip per this many seconds, so a burst of ingested fixes
    | can't overwhelm the websocket layer or dashboards. Set 0 to broadcast every
    | batch (not recommended in production).
    |
    */

    'tracking' => [
        'broadcast_coalesce_seconds' => (int) env('SENTRIX_LOCATION_BROADCAST_COALESCE', 2),

        // An active trip whose last fix is older than this is considered to have
        // gone dark — the staleness sweep flags it and (by default) escalates.
        'stale_after_seconds' => (int) env('SENTRIX_LOCATION_STALE_AFTER', 300),
    ],

];
