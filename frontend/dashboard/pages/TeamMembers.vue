<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    owner: { type: Object, default: () => ({}) },
    members: { type: Array, default: () => [] },
    isOwner: { type: Boolean, default: false },
    memberLimit: { type: Number, default: 10 },
    baseUrl: { type: String, default: '/' },
    csrfToken: { type: String, default: '' },
    flashes: { type: Array, default: () => [] }
});

const showAddForm = ref(false);
const passwordFieldType = ref('password');

const atLimit = computed(() => props.members.length >= props.memberLimit);

function date(value) {
    if (!value) return '';
    const parsed = new Date(String(value).replace(' ', 'T'));
    return Number.isNaN(parsed.getTime())
        ? value
        : parsed.toLocaleDateString(undefined, { dateStyle: 'medium' });
}

function confirmRemove(member, event) {
    if (!window.confirm(`Remove ${member.name}? Their login will be deactivated immediately.`)) {
        event.preventDefault();
    }
}
</script>

<template>
    <div class="container py-4 ds-page page-shell">
        <div v-for="(flash, index) in flashes" :key="index" class="alert alert-dismissible fade show mb-3" :class="flash.type === 'error' ? 'alert-danger' : `alert-${flash.type}`" role="status">
            <i class="bi me-2" :class="flash.type === 'error' ? 'bi-exclamation-circle-fill' : (flash.type === 'success' ? 'bi-check-circle-fill' : 'bi-info-circle-fill')" aria-hidden="true"></i>
            {{ flash.message }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>

        <header class="ds-page-header page-header mb-4">
            <div>
                <p class="ds-eyebrow">Business account</p>
                <h1 class="page-title mb-1">Team members</h1>
                <p class="text-muted mb-0 page-description">
                    People who can create and track deliveries for <strong>{{ owner.name || 'this account' }}</strong>.
                </p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <button
                    v-if="isOwner && !atLimit && !showAddForm"
                    type="button"
                    class="btn btn-primary"
                    @click="showAddForm = true"
                >
                    <i class="bi bi-person-plus me-1" aria-hidden="true"></i>Add team member
                </button>
                <a class="btn btn-outline-secondary" :href="`${baseUrl}dashboard`">Back to dashboard</a>
            </div>
        </header>

        <div v-if="!isOwner" class="alert alert-info" role="status">
            <i class="bi bi-info-circle me-1" aria-hidden="true"></i>
            You are signed in as a team member. Only the account owner can add or remove members — you can still create and track deliveries for this account.
        </div>

        <div v-if="atLimit && isOwner" class="alert alert-warning" role="status">
            <i class="bi bi-people me-1" aria-hidden="true"></i>
            This account has reached the {{ memberLimit }}-member limit. Remove a member before adding another.
        </div>

        <section v-if="isOwner && showAddForm" class="surface ds-panel p-3 p-md-4 mb-4" aria-labelledby="add-member-heading">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="h5 mb-0" id="add-member-heading">Add a team member</h2>
                <button type="button" class="btn-close" aria-label="Close add member form" @click="showAddForm = false"></button>
            </div>
            <form method="post" :action="`${baseUrl}team/members`" novalidate>
                <input type="hidden" name="csrf_token" :value="csrfToken">
                <div class="row g-3">
                    <div class="col-12 col-md-6 shared-field-wrap">
                        <label for="member-name" class="form-label shared-label"><span>Full name</span><span class="text-danger ms-1" aria-hidden="true">*</span></label>
                        <input id="member-name" name="name" type="text" class="form-control shared-input" maxlength="100" required>
                    </div>
                    <div class="col-12 col-md-6 shared-field-wrap">
                        <label for="member-email" class="form-label shared-label"><span>Email (used to sign in)</span><span class="text-danger ms-1" aria-hidden="true">*</span></label>
                        <input id="member-email" name="email" type="email" class="form-control shared-input" maxlength="150" required autocomplete="off">
                    </div>
                    <div class="col-12 col-md-6 shared-field-wrap">
                        <label for="member-phone" class="form-label shared-label"><span>Mobile number <span class="text-muted fw-normal">(optional)</span></span></label>
                        <input id="member-phone" name="phone" type="tel" class="form-control shared-input" maxlength="30" placeholder="09XXXXXXXXX">
                    </div>
                    <div class="col-12 col-md-6 shared-field-wrap">
                        <label for="member-password" class="form-label shared-label"><span>Temporary password</span><span class="text-danger ms-1" aria-hidden="true">*</span></label>
                        <input id="member-password" name="password" :type="passwordFieldType" class="form-control shared-input" minlength="8" required autocomplete="new-password">
                        <div class="form-text shared-helper-text">At least 8 characters with a letter and a number. Share it with the member securely.</div>
                    </div>
                    <div class="col-12 col-md-6 shared-field-wrap">
                        <label for="member-password-confirm" class="form-label shared-label"><span>Confirm password</span><span class="text-danger ms-1" aria-hidden="true">*</span></label>
                        <input id="member-password-confirm" name="password_confirm" :type="passwordFieldType" class="form-control shared-input" minlength="8" required autocomplete="new-password">
                    </div>
                    <div class="col-12 col-md-6 d-flex align-items-end">
                        <div class="form-check">
                            <input id="show-member-password" class="form-check-input" type="checkbox" @change="passwordFieldType = $event.target.checked ? 'text' : 'password'">
                            <label class="form-check-label" for="show-member-password">Show password</label>
                        </div>
                    </div>
                </div>
                <div class="d-flex gap-2 mt-3">
                    <button class="btn btn-primary" type="submit">Create member login</button>
                    <button class="btn btn-outline-secondary" type="button" @click="showAddForm = false">Cancel</button>
                </div>
            </form>
        </section>

        <section aria-labelledby="members-heading">
            <h2 class="h5 mb-3" id="members-heading">Members ({{ members.length + 1 }})</h2>

            <ul class="surface list-unstyled mb-0">
                <li class="ds-member-row">
                    <div class="ds-member-avatar" aria-hidden="true"><i class="bi bi-person-circle"></i></div>
                    <div class="ds-member-main min-w-0">
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <span class="fw-semibold">{{ owner.name || 'Account owner' }}</span>
                            <span class="badge text-bg-primary">Owner</span>
                        </div>
                        <span class="small text-muted text-break">{{ owner.email }}</span>
                    </div>
                    <div class="small text-muted text-nowrap d-none d-md-block">Full access</div>
                </li>
                <li v-for="member in members" :key="member.id" class="ds-member-row">
                    <div class="ds-member-avatar" aria-hidden="true"><i class="bi bi-person"></i></div>
                    <div class="ds-member-main min-w-0">
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <span class="fw-semibold">{{ member.name }}</span>
                            <span v-if="member.status !== 'active'" class="badge text-bg-secondary">Deactivated</span>
                        </div>
                        <span class="small text-muted text-break">{{ member.email }}<template v-if="member.phone"> · {{ member.phone }}</template></span>
                    </div>
                    <div class="small text-muted text-nowrap d-none d-md-block">Added {{ date(member.created_at) }}</div>
                    <form
                        v-if="isOwner"
                        method="post"
                        :action="`${baseUrl}team/members/${member.id}/delete`"
                        class="ds-member-action"
                        @submit="confirmRemove(member, $event)"
                    >
                        <input type="hidden" name="csrf_token" :value="csrfToken">
                        <button class="btn btn-sm btn-outline-danger" type="submit">Remove</button>
                    </form>
                </li>
            </ul>

            <p v-if="members.length === 0" class="text-muted small mt-2 mb-0">
                No team members yet. Add bookers so your colleagues can place deliveries under this account.
            </p>
        </section>
    </div>
</template>

<style scoped>
.ds-member-row {
    display: flex;
    align-items: center;
    gap: var(--s-3, 12px);
    padding: var(--s-3, 12px) var(--s-4, 16px);
    border-bottom: 1px solid var(--paper, #EEF2F5);
}

.ds-member-row:last-child {
    border-bottom: 0;
}

.ds-member-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: color-mix(in srgb, var(--route, #087F98) 12%, transparent);
    color: var(--route, #087F98);
    font-size: 1.25rem;
    flex-shrink: 0;
}

.ds-member-main {
    flex: 1 1 auto;
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.ds-member-action {
    flex-shrink: 0;
}
</style>
