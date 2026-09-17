<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Layers, Pencil, Plus, Power, Trash2, X } from 'lucide-vue-next';
import { ref } from 'vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import subscriptionPackages from '@/routes/admin/subscription-packages';

type RenewalCycle = 'one_off' | 'daily' | 'weekly' | 'bi_weekly' | 'monthly';

type SubscriptionPackage = {
    id: number;
    name: string;
    slug: string;
    renewal_cycle: RenewalCycle;
    renewal_cycle_label: string;
    points_allocated: number;
    price_naira: number;
    is_active: boolean;
    sort_order: number;
};

defineProps<{
    packages: SubscriptionPackage[];
    renewalCycles: { value: RenewalCycle; label: string }[];
}>();

function emptyForm() {
    return {
        name: '',
        slug: '',
        renewal_cycle: 'monthly' as RenewalCycle,
        points_allocated: 0,
        price_naira: 0,
        sort_order: 0,
        is_active: true as boolean,
    };
}

const isCreating = ref(false);
const editingId = ref<number | null>(null);

const createForm = useForm(emptyForm());
const editForm = useForm(emptyForm());

function slugify(value: string): string {
    return value
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/(^-|-$)/g, '');
}

function startCreate(): void {
    isCreating.value = true;
    editingId.value = null;
    createForm.reset();
    createForm.clearErrors();
}

function cancelCreate(): void {
    isCreating.value = false;
    createForm.reset();
    createForm.clearErrors();
}

function submitCreate(): void {
    if (!createForm.slug) {
        createForm.slug = slugify(createForm.name);
    }

    createForm.post(subscriptionPackages.store.url(), {
        preserveScroll: true,
        onSuccess: () => {
            isCreating.value = false;
            createForm.reset();
        },
    });
}

function startEdit(pkg: SubscriptionPackage): void {
    isCreating.value = false;
    editingId.value = pkg.id;
    editForm.name = pkg.name;
    editForm.slug = pkg.slug;
    editForm.renewal_cycle = pkg.renewal_cycle;
    editForm.points_allocated = pkg.points_allocated;
    editForm.price_naira = pkg.price_naira;
    editForm.sort_order = pkg.sort_order;
    editForm.is_active = pkg.is_active;
    editForm.clearErrors();
}

function cancelEdit(): void {
    editingId.value = null;
    editForm.clearErrors();
}

function submitEdit(pkg: SubscriptionPackage): void {
    editForm.patch(subscriptionPackages.update.url(pkg.id), {
        preserveScroll: true,
        onSuccess: () => {
            editingId.value = null;
        },
    });
}

function toggleActive(pkg: SubscriptionPackage): void {
    router.post(
        subscriptionPackages.toggleActive.url(pkg.id),
        {},
        { preserveScroll: true },
    );
}

function destroy(pkg: SubscriptionPackage): void {
    if (!confirm(`Delete the "${pkg.name}" package?`)) {
        return;
    }

    router.delete(subscriptionPackages.destroy.url(pkg.id), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Admin - Subscription Packages" />

    <AdminLayout :breadcrumbs="[{ title: 'Subscription Packages' }]">
        <div class="flex flex-col gap-6 font-sans text-xs">
            <div
                class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center"
            >
                <div>
                    <h1
                        class="flex items-center gap-2 text-2xl font-black tracking-tight text-primary uppercase"
                    >
                        <Layers class="h-6 w-6 text-primary" />
                        <span>Subscription Packages</span>
                    </h1>
                    <p class="mt-1 text-xs text-on-surface-variant">
                        Manage the fixed-tier point packages users can subscribe
                        to from the Wallet page.
                    </p>
                </div>

                <button
                    v-if="!isCreating"
                    type="button"
                    class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-xs font-black text-on-primary uppercase shadow-sm transition-all hover:bg-tertiary-container"
                    @click="startCreate"
                >
                    <Plus class="h-4 w-4" />
                    Add package
                </button>
            </div>

            <form
                v-if="isCreating"
                class="grid grid-cols-1 gap-3 rounded-xl border border-outline-variant bg-surface-container-lowest p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-6"
                @submit.prevent="submitCreate"
            >
                <div class="lg:col-span-1">
                    <label
                        class="mb-1 block text-[10px] font-bold text-on-surface-variant uppercase"
                        >Name</label
                    >
                    <input
                        v-model="createForm.name"
                        type="text"
                        required
                        class="w-full rounded-lg border border-outline-variant bg-surface-container-low px-3 py-2 text-xs font-bold text-on-surface focus:ring-1 focus:ring-secondary focus:outline-none"
                    />
                    <p v-if="createForm.errors.name" class="mt-1 text-error">
                        {{ createForm.errors.name }}
                    </p>
                </div>

                <div class="lg:col-span-1">
                    <label
                        class="mb-1 block text-[10px] font-bold text-on-surface-variant uppercase"
                        >Slug</label
                    >
                    <input
                        v-model="createForm.slug"
                        type="text"
                        :placeholder="slugify(createForm.name) || 'auto'"
                        class="w-full rounded-lg border border-outline-variant bg-surface-container-low px-3 py-2 text-xs font-bold text-on-surface focus:ring-1 focus:ring-secondary focus:outline-none"
                    />
                    <p v-if="createForm.errors.slug" class="mt-1 text-error">
                        {{ createForm.errors.slug }}
                    </p>
                </div>

                <div class="lg:col-span-1">
                    <label
                        class="mb-1 block text-[10px] font-bold text-on-surface-variant uppercase"
                        >Renewal cycle</label
                    >
                    <select
                        v-model="createForm.renewal_cycle"
                        class="w-full rounded-lg border border-outline-variant bg-surface-container-low px-3 py-2 text-xs font-bold text-on-surface uppercase focus:ring-1 focus:ring-secondary focus:outline-none"
                    >
                        <option
                            v-for="cycle in renewalCycles"
                            :key="cycle.value"
                            :value="cycle.value"
                        >
                            {{ cycle.label }}
                        </option>
                    </select>
                </div>

                <div class="lg:col-span-1">
                    <label
                        class="mb-1 block text-[10px] font-bold text-on-surface-variant uppercase"
                        >Points</label
                    >
                    <input
                        v-model.number="createForm.points_allocated"
                        type="number"
                        min="1"
                        required
                        class="w-full rounded-lg border border-outline-variant bg-surface-container-low px-3 py-2 text-xs font-bold text-on-surface focus:ring-1 focus:ring-secondary focus:outline-none"
                    />
                    <p
                        v-if="createForm.errors.points_allocated"
                        class="mt-1 text-error"
                    >
                        {{ createForm.errors.points_allocated }}
                    </p>
                </div>

                <div class="lg:col-span-1">
                    <label
                        class="mb-1 block text-[10px] font-bold text-on-surface-variant uppercase"
                        >Price (₦)</label
                    >
                    <input
                        v-model.number="createForm.price_naira"
                        type="number"
                        min="0.01"
                        step="0.01"
                        required
                        class="w-full rounded-lg border border-outline-variant bg-surface-container-low px-3 py-2 text-xs font-bold text-on-surface focus:ring-1 focus:ring-secondary focus:outline-none"
                    />
                    <p
                        v-if="createForm.errors.price_naira"
                        class="mt-1 text-error"
                    >
                        {{ createForm.errors.price_naira }}
                    </p>
                </div>

                <div class="flex items-end gap-2 lg:col-span-1">
                    <button
                        type="submit"
                        :disabled="createForm.processing"
                        class="flex-1 rounded-lg bg-secondary px-4 py-2 text-[10px] font-black text-on-secondary-fixed uppercase disabled:opacity-60"
                    >
                        Save
                    </button>
                    <button
                        type="button"
                        class="rounded-lg border border-outline-variant p-2 text-on-surface-variant hover:bg-surface-container"
                        @click="cancelCreate"
                    >
                        <X class="h-4 w-4" />
                    </button>
                </div>
            </form>

            <div
                class="flex flex-col overflow-hidden rounded-xl border border-outline-variant bg-surface-container-lowest shadow-sm"
            >
                <div class="overflow-x-auto">
                    <table
                        class="w-full min-w-[900px] border-collapse text-left"
                    >
                        <thead
                            class="border-b border-outline-variant bg-surface-container-low"
                        >
                            <tr
                                class="text-[10px] font-bold tracking-widest text-on-surface-variant uppercase"
                            >
                                <th class="px-6 py-4">Package</th>
                                <th class="px-6 py-4">Cycle</th>
                                <th class="px-6 py-4 text-right">Points</th>
                                <th class="px-6 py-4 text-right">Price</th>
                                <th class="px-6 py-4">Status</th>
                                <th class="px-6 py-4 text-right">Operations</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant">
                            <template v-for="pkg in packages" :key="pkg.id">
                                <tr
                                    v-if="editingId !== pkg.id"
                                    class="group transition-colors hover:bg-surface-container-low/20"
                                >
                                    <td class="px-6 py-4">
                                        <span class="font-bold text-primary">{{
                                            pkg.name
                                        }}</span>
                                        <span
                                            class="block text-[9px] text-on-surface-variant"
                                            >{{ pkg.slug }}</span
                                        >
                                    </td>
                                    <td class="px-6 py-4 uppercase">
                                        {{ pkg.renewal_cycle_label }}
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        {{
                                            pkg.points_allocated.toLocaleString()
                                        }}
                                    </td>
                                    <td
                                        class="px-6 py-4 text-right font-bold text-primary"
                                    >
                                        ₦{{ pkg.price_naira.toLocaleString() }}
                                    </td>
                                    <td class="px-6 py-4">
                                        <span
                                            :class="[
                                                'rounded-full border px-2.5 py-1 text-[9px] font-black tracking-widest uppercase',
                                                pkg.is_active
                                                    ? 'border-secondary/20 bg-secondary-container text-on-secondary-container'
                                                    : 'border-outline-variant bg-surface-container text-on-surface-variant',
                                            ]"
                                        >
                                            {{
                                                pkg.is_active
                                                    ? 'Active'
                                                    : 'Inactive'
                                            }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <div class="flex justify-end gap-2">
                                            <button
                                                class="inline-flex items-center gap-1.5 rounded-lg border border-outline-variant px-2.5 py-1.5 text-[10px] font-black text-on-surface-variant uppercase transition-colors hover:bg-surface-container"
                                                @click="startEdit(pkg)"
                                            >
                                                <Pencil class="h-3.5 w-3.5" />
                                                <span>Edit</span>
                                            </button>
                                            <button
                                                class="inline-flex items-center gap-1.5 rounded-lg border border-outline-variant px-2.5 py-1.5 text-[10px] font-black text-on-surface-variant uppercase transition-colors hover:bg-surface-container"
                                                @click="toggleActive(pkg)"
                                            >
                                                <Power class="h-3.5 w-3.5" />
                                                <span>{{
                                                    pkg.is_active
                                                        ? 'Deactivate'
                                                        : 'Activate'
                                                }}</span>
                                            </button>
                                            <button
                                                class="inline-flex items-center gap-1.5 rounded-lg border border-error/30 px-2.5 py-1.5 text-[10px] font-black text-error uppercase transition-colors hover:bg-error-container/30"
                                                @click="destroy(pkg)"
                                            >
                                                <Trash2 class="h-3.5 w-3.5" />
                                                <span>Delete</span>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-else class="bg-surface-container-low/40">
                                    <td colspan="6" class="px-6 py-4">
                                        <form
                                            class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-6"
                                            @submit.prevent="submitEdit(pkg)"
                                        >
                                            <div>
                                                <label
                                                    class="mb-1 block text-[10px] font-bold text-on-surface-variant uppercase"
                                                    >Name</label
                                                >
                                                <input
                                                    v-model="editForm.name"
                                                    type="text"
                                                    required
                                                    class="w-full rounded-lg border border-outline-variant bg-surface-container-lowest px-3 py-2 text-xs font-bold text-on-surface focus:ring-1 focus:ring-secondary focus:outline-none"
                                                />
                                            </div>
                                            <div>
                                                <label
                                                    class="mb-1 block text-[10px] font-bold text-on-surface-variant uppercase"
                                                    >Slug</label
                                                >
                                                <input
                                                    v-model="editForm.slug"
                                                    type="text"
                                                    required
                                                    class="w-full rounded-lg border border-outline-variant bg-surface-container-lowest px-3 py-2 text-xs font-bold text-on-surface focus:ring-1 focus:ring-secondary focus:outline-none"
                                                />
                                            </div>
                                            <div>
                                                <label
                                                    class="mb-1 block text-[10px] font-bold text-on-surface-variant uppercase"
                                                    >Renewal cycle</label
                                                >
                                                <select
                                                    v-model="
                                                        editForm.renewal_cycle
                                                    "
                                                    class="w-full rounded-lg border border-outline-variant bg-surface-container-lowest px-3 py-2 text-xs font-bold text-on-surface uppercase focus:ring-1 focus:ring-secondary focus:outline-none"
                                                >
                                                    <option
                                                        v-for="cycle in renewalCycles"
                                                        :key="cycle.value"
                                                        :value="cycle.value"
                                                    >
                                                        {{ cycle.label }}
                                                    </option>
                                                </select>
                                            </div>
                                            <div>
                                                <label
                                                    class="mb-1 block text-[10px] font-bold text-on-surface-variant uppercase"
                                                    >Points</label
                                                >
                                                <input
                                                    v-model.number="
                                                        editForm.points_allocated
                                                    "
                                                    type="number"
                                                    min="1"
                                                    required
                                                    class="w-full rounded-lg border border-outline-variant bg-surface-container-lowest px-3 py-2 text-xs font-bold text-on-surface focus:ring-1 focus:ring-secondary focus:outline-none"
                                                />
                                            </div>
                                            <div>
                                                <label
                                                    class="mb-1 block text-[10px] font-bold text-on-surface-variant uppercase"
                                                    >Price (₦)</label
                                                >
                                                <input
                                                    v-model.number="
                                                        editForm.price_naira
                                                    "
                                                    type="number"
                                                    min="0.01"
                                                    step="0.01"
                                                    required
                                                    class="w-full rounded-lg border border-outline-variant bg-surface-container-lowest px-3 py-2 text-xs font-bold text-on-surface focus:ring-1 focus:ring-secondary focus:outline-none"
                                                />
                                            </div>
                                            <div class="flex items-end gap-2">
                                                <button
                                                    type="submit"
                                                    :disabled="
                                                        editForm.processing
                                                    "
                                                    class="flex-1 rounded-lg bg-secondary px-4 py-2 text-[10px] font-black text-on-secondary-fixed uppercase disabled:opacity-60"
                                                >
                                                    Save
                                                </button>
                                                <button
                                                    type="button"
                                                    class="rounded-lg border border-outline-variant p-2 text-on-surface-variant hover:bg-surface-container"
                                                    @click="cancelEdit"
                                                >
                                                    <X class="h-4 w-4" />
                                                </button>
                                            </div>
                                        </form>
                                    </td>
                                </tr>
                            </template>
                            <tr v-if="packages.length === 0">
                                <td
                                    colspan="6"
                                    class="px-6 py-12 text-center font-bold tracking-widest text-on-surface-variant uppercase"
                                >
                                    No subscription packages yet.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
