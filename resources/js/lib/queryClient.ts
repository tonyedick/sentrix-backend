import { QueryClient } from '@tanstack/react-query';
import { ApiError } from '@/lib/api';

export const queryClient = new QueryClient({
    defaultOptions: {
        queries: {
            staleTime: 15_000,
            refetchOnWindowFocus: true,
            retry: (failureCount, error) => {
                // Never retry auth/permission/validation errors — only transient ones.
                if (error instanceof ApiError && [401, 403, 404, 422].includes(error.status)) {
                    return false;
                }
                return failureCount < 2;
            },
        },
        mutations: {
            retry: false,
        },
    },
});
