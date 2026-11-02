<template>
  <p v-if="!boards.length" class="note">Досок пока нет.</p>
  <table v-else class="platform-table" aria-label="Все доски сервиса">
    <thead>
      <tr>
        <th scope="col">Доска</th>
        <th scope="col">Владелец</th>
        <th scope="col">Видимость</th>
        <th scope="col" class="platform-table-num">Посты</th>
        <th scope="col" class="platform-table-num">Участники</th>
        <th scope="col">Последний пост</th>
        <th scope="col">Создана</th>
      </tr>
    </thead>
    <tbody>
      <tr v-for="b in boards" :key="b.id" :class="{ trashed: b.deleted_at }">
        <td>
          <div class="platform-cell-main">
            <a v-if="!b.deleted_at" :href="`/${b.slug}`" target="_blank" rel="noopener">{{ b.name }}</a>
            <span v-else>{{ b.name }}</span>
          </div>
          <div class="platform-cell-sub">
            /{{ b.slug }}
            <span v-if="b.deleted_at" class="platform-flag">в корзине с {{ fmtDate(b.deleted_at) }}</span>
            <span v-else-if="!b.published" class="platform-flag">не опубликована</span>
          </div>
        </td>
        <td>
          <template v-if="b.owner">
            <div class="platform-cell-main">{{ b.owner.name }}</div>
            <div class="platform-cell-sub">{{ b.owner.email }}</div>
          </template>
          <span v-else class="platform-cell-sub">из консоли</span>
        </td>
        <td>{{ b.visibility === 'private' ? 'Закрытая' : 'Открытая' }}</td>
        <td class="platform-table-num">{{ b.posts_count }}</td>
        <td class="platform-table-num">{{ b.members_count }}</td>
        <td class="platform-cell-sub">{{ fmtTimeAgo(b.last_post_at) }}</td>
        <td class="platform-cell-sub">{{ fmtDate(b.created_at) }}</td>
      </tr>
    </tbody>
  </table>
</template>

<script setup lang="ts">
import type { PlatformBoard } from '@/api/platform'
import { fmtDate, fmtTimeAgo } from '@/shared/utils/format'

// «Доски» in the service admin: every board with its owner and counts; the trashed ones dimmed, without a link.
defineProps<{
  boards: PlatformBoard[]
}>()
</script>