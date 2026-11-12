<template>
  <div class="container page-shell py-4">
    <nav class="mb-3"><a :href="`${baseUrl}admin/users`">Users</a><span class="text-muted"> / User #{{ user.id }}</span></nav>
    <header class="page-header mb-4">
      <div>
        <p class="ds-eyebrow">Administration</p>
        <h1 class="page-title">User account</h1>
        <p class="page-description">Review or update account access and contact details.</p>
      </div>
    </header>

    <div v-for="(flash, index) in flashes" :key="index" class="alert" :class="flash.type === 'error' ? 'alert-danger' : `alert-${flash.type}`" role="status">
      {{ flash.message }}
    </div>

    <section class="surface surface-section" aria-labelledby="user-edit-heading">
      <h2 class="h5 mb-4" id="user-edit-heading">Edit user #{{ user.id }}</h2>
      <form :action="`${baseUrl}admin/users/${user.id}/update`" method="POST">
        <input type="hidden" name="csrf_token" :value="csrfToken">
        <div class="row g-3">
          <div class="col-12 col-md-6">
            <label class="form-label" for="user-name">Full name</label>
            <input class="form-control" id="user-name" name="name" type="text" minlength="2" maxlength="100" required :value="user.name">
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label" for="user-email">Email address</label>
            <input class="form-control" id="user-email" name="email" type="email" maxlength="150" required :value="user.email">
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label" for="user-phone">Phone number</label>
            <input class="form-control" id="user-phone" name="phone" type="tel" maxlength="30" :value="user.phone || ''">
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label" for="user-role">Role</label>
            <select class="form-select" id="user-role" name="role_id" required :disabled="isCurrentUser">
              <option v-for="role in roles" :key="role.id" :value="role.id" :selected="Number(user.role_id) === Number(role.id)">{{ role.name }}</option>
            </select>
            <input v-if="isCurrentUser" type="hidden" name="role_id" :value="user.role_id">
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label" for="user-status">Status</label>
            <select class="form-select" id="user-status" name="status" required :disabled="isCurrentUser">
              <option value="active" :selected="user.status === 'active'">Active</option>
              <option value="inactive" :selected="user.status === 'inactive'">Inactive</option>
            </select>
            <input v-if="isCurrentUser" type="hidden" name="status" value="active">
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label" for="user-password">New password</label>
            <input class="form-control" id="user-password" name="password" type="password" minlength="8" autocomplete="new-password">
          </div>
        </div>
        <div class="d-flex flex-wrap gap-2 mt-4">
          <button class="btn btn-primary" type="submit">Save changes</button>
          <a class="btn btn-outline-secondary" :href="`${baseUrl}admin/users`">Back to users</a>
        </div>
      </form>
    </section>

    <section class="surface surface-section mt-4">
      <h2 class="h5">Account information</h2>
      <dl class="row mb-0">
        <dt class="col-sm-3">Customer company</dt><dd class="col-sm-9">{{ user.company_name || '—' }}</dd>
        <dt class="col-sm-3">Created</dt><dd class="col-sm-9">{{ user.created_at || '—' }}</dd>
      </dl>
    </section>

    <section v-if="!isCurrentUser" class="mt-4">
      <form :action="`${baseUrl}admin/users/${user.id}/delete`" method="POST" onsubmit="return confirm('Remove this account?')">
        <input type="hidden" name="csrf_token" :value="csrfToken">
        <button type="submit" class="btn btn-outline-danger">Delete user account</button>
      </form>
    </section>
  </div>
</template>

<script setup>
const props = defineProps({
  user: { type: Object, default: () => ({}) },
  roles: { type: Array, default: () => [] },
  baseUrl: { type: String, default: '/' },
  csrfToken: { type: String, default: '' },
  isCurrentUser: { type: Boolean, default: false },
  flashes: { type: Array, default: () => [] }
});
</script>