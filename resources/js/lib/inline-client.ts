import { computed, ref } from 'vue';
export type ClientOption = { id: number; name: string };

export function useInlineClientOptions(existing: () => ClientOption[], select: (id: number) => void) {
    const added = ref<ClientOption[]>([]);
    const options = computed(() => [...new Map([...existing(), ...added.value].map(client => [client.id, client])).values()]
        .sort((a, b) => a.name.localeCompare(b.name, 'pt-BR')));
    function created(client: ClientOption) {
        added.value.push(client);
        select(client.id);
    }
    return { options, created };
}
