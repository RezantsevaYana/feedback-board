<template>
  <p v-if="!users.length" class="note">Пользователей пока нет.</p>
  <table v-else class="platform-table" aria-label="Все пользователи сервиса">
    <thead>
      <tr>
        <th scope="col">Человек</th>
        <th scope="col">Почта</th>
        <th scope="col">Доски</th>
        <th scope="col">Регистрация</th>
      </tr>
    </thead>
    <tbody>
      <tr v-for="u in users" :key="u.id">
        <td>
          <div class="platform-cell-main">
            {{ u.name }}
            <span v-if="u.is_superadmin" class="platform-flag">админ сервиса</span>
          </div>
          <div class="platform-cell-sub">{{ u.email }}</div>
        </td>
        <td>
          <span v-if="u.email_verified" class="platform-cell-sub">подтверждена</span>
          <span v-else class="platform-flag">не подтверждена</span>
        </td>
        <td>
          <span v-if="!u.boards.length" class="platform-cell-sub">—</span>
          <ul v-else class="platform-boards">
            <li v-for="b in u.boards" :key="b.slug">
              <a :href="`/${b.slug}`" target="_blank" rel="noopener">{{ b.name }}</a>
              <span class="platform-cell-sub"> · {{ ROLE_LABELS[b.role].toLowerCase() }}</span>
            </li>
          </ul>
        </td>
        <td class="platform-cell-sub">{{ fmtDate(u.created_at) }}</td>
      </tr>
    </tbody>
  </table>
</template>

<script setup lang="ts">
import type { PlatformUser } from '@/api/platform'
import { ROLE_LABELS } from '@/api/types'
import { fmtDate } from '@/shared/utils/format'

// «Пользователи» in the service admin: every account, whether the email is confirmed, its boards and roles.
defineProps<{
  users: PlatformUser[]
}>()
</script>