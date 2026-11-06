<template>
  <!-- open board: both ways in at once — the link and the button on the product's site -->
  <div v-if="visibility === 'public'" class="onboarding-step">
    <h1>Как пользователи попадут на доску?</h1>
    <p class="onboarding-lead">Работают оба способа сразу. Всё, что пришлют, окажется на этой доске.</p>

    <p v-if="!embed" class="note">Загружаем…</p>
    <template v-else>
      <section class="onboarding-way">
        <h2>Ссылка <span class="onboarding-way-note">для чатов и писем</span></h2>
        <div class="onboarding-share">
          <code>{{ embed.direct_url }}</code>
          <CopyButton :value="embed.direct_url" label="Скопировать" />
        </div>
      </section>

      <section class="onboarding-way">
        <h2>Кнопка на сайте <span class="onboarding-way-note">для тех, кто сейчас в продукте · можно позже</span></h2>
        <p class="note">
          Пользователь жмёт «Обратная связь» прямо в продукте и не уходит с сайта. Укажите домен и вставьте тег перед
          закрывающим <code class="mono">&lt;/body&gt;</code>; цвет и текст кнопки настроите потом.
        </p>
        <!-- the same name and words as «Домены» in the settings: the widget works only from the sites listed there -->
        <InputComponent
          v-model="origin"
          label="Домены"
          placeholder="https://app.example.com"
          type="url"
          autocomplete="off"
          :error="originError"
          @input="originError = ''"
        />
        <p class="note">Сайт, где будет стоять виджет: схема и хост, без пути. Без доменов виджет не сможет отправлять предложения. Другие сайты добавите в настройках.</p>
        <pre class="onboarding-code"><code>{{ embed?.script_tag }}</code></pre>
        <CopyButton :value="embed?.script_tag ?? ''" label="Скопировать код" class="onboarding-copy" />
      </section>
    </template>
  </div>

  <!-- closed board: only the invited get in -->
  <div v-else class="onboarding-step">
    <h1>Кого пригласить?</h1>
    <p class="onboarding-lead">Доску увидят только приглашённые. Им придёт письмо со ссылкой. Можно пропустить и позвать людей потом.</p>
    <TextAreaComponent
      v-model="raw"
      label="Почты через запятую или по строкам"
      rows="4"
      placeholder="anna@company.ru, oleg@company.ru"
      :error="emailsError"
      @input="sentError = ''"
    />
    <SelectComponent v-model="role" label="Роль" :options="roleOptions" />
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { type BoardVisibility, BoardsApi, type EmbedInfo } from '@/api/boards'
import { ApiError, errorMessage } from '@/api/client'
import { InvitationsApi } from '@/api/participants'
import { ASSIGNABLE_ROLES, ROLE_HINTS, ROLE_LABELS, type ProjectRole } from '@/api/types'
import { useNotificationStore } from '@/store/modals/useNotificationStore'
import { invalidEmailsMessage, parseEmails } from '@/shared/utils/emails'
import { plural } from '@/shared/utils/format'
import CopyButton from '@/shared/ui/CopyButton.vue'
import InputComponent from '@/shared/ui/InputComponent.vue'
import SelectComponent from '@/shared/ui/SelectComponent.vue'
import TextAreaComponent from '@/shared/ui/TextAreaComponent.vue'

// Onboarding step 5. Open board: the link and the widget code with one site for it (more sites, colour and label —
// in the settings later). Closed board: invitations by email with one role. Both are optional: an empty field
// just moves on.
const props = defineProps<{
  slug: string
  visibility: BoardVisibility
}>()
const notification = useNotificationStore()

const embed = ref<EmbedInfo | null>(null)
watch(() => [props.slug, props.visibility], async () => {
  if (props.visibility !== 'public' || embed.value) return
  try {
    embed.value = (await BoardsApi.embed(props.slug)).data
  } catch (e) {
    notification.error(errorMessage(e))
  }
}, { immediate: true })

// open board: one site for the widget
const origin = ref('')
const originError = ref('')
const ORIGIN_FORMAT = /^https?:\/\/[^/\s]+$/

async function saveOrigin(): Promise<boolean> {
  const value = origin.value.trim().replace(/\/+$/, '')
  if (!value) return true
  if (!ORIGIN_FORMAT.test(value)) {
    originError.value = 'Домен — схема и хост без пути, например https://app.example.com'
    return false
  }
  const origins = [...new Set([...(embed.value?.allowed_origins ?? []), value])]
  try {
    await BoardsApi.update(props.slug, { origins })
    return true
  } catch (e) {
    originError.value = e instanceof ApiError ? (e.field('origins.0') ?? e.field('origins') ?? e.message) : errorMessage(e)
    return false
  }
}

// closed board: invitations
type AssignableRole = Exclude<ProjectRole, 'owner'>
const roleOptions = ASSIGNABLE_ROLES.map((value) => ({ value, label: ROLE_LABELS[value], hint: ROLE_HINTS[value] }))
const role = ref<AssignableRole>('voter')
const raw = ref('')
const sentError = ref('')
const parsed = computed(() => parseEmails(raw.value))
const emailsError = computed(() => sentError.value || invalidEmailsMessage(parsed.value.invalid))

async function sendInvites(): Promise<boolean> {
  if (parsed.value.invalid.length) return false
  if (!parsed.value.valid.length) return true
  try {
    const result = await InvitationsApi.create(props.slug, {
      role: role.value,
      invitees: parsed.value.valid.map((email) => ({ email })),
    })
    const sent = result.data.length
    if (sent) notification.success(`Отправили ${sent} ${plural(sent, 'приглашение', 'приглашения', 'приглашений')}`)
    return true
  } catch (e) {
    sentError.value = errorMessage(e)
    return false
  }
}

async function submit(): Promise<boolean> {
  return props.visibility === 'public' ? saveOrigin() : sendInvites()
}
defineExpose({ submit })
</script>
