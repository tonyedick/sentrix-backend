import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { api } from '@/lib/api';
import type { NotificationItem, Paginated } from '@/types/api';

export const notificationKeys = {
    list: (unreadOnly: boolean) => ['notifications', { unreadOnly }] as const,
    unreadCount: () => ['notifications-unread-count'] as const,
};

export function useNotifications(unreadOnly: boolean) {
    return useQuery({
        queryKey: notificationKeys.list(unreadOnly),
        queryFn: async () => {
            const res = await api.get<NotificationItem[]>('/notifications', unreadOnly ? { unread: true } : undefined);
            return { data: res.data, meta: res.meta, links: res.links } as Paginated<NotificationItem>;
        },
    });
}

export function useUnreadCount() {
    return useQuery({
        queryKey: notificationKeys.unreadCount(),
        queryFn: async () => (await api.get<{ count: number }>('/notifications/unread-count')).data.count,
        staleTime: 10_000,
    });
}

export function useMarkNotificationRead() {
    const qc = useQueryClient();
    return useMutation({
        mutationFn: async (id: string) => (await api.post(`/notifications/${id}/read`)).data,
        onSuccess: () => qc.invalidateQueries({ queryKey: ['notifications'] }),
    });
}

export function useMarkAllNotificationsRead() {
    const qc = useQueryClient();
    return useMutation({
        mutationFn: async () => (await api.post('/notifications/read-all')).data,
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: ['notifications'] });
            qc.invalidateQueries({ queryKey: ['notifications-unread-count'] });
        },
    });
}
