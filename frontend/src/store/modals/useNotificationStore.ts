import { defineStore } from 'pinia'

const DURATION = {
  success: 2200,
  error: 4000,
}

// One short message at the bottom of the screen (rendered in App.vue).
export const useNotificationStore = defineStore('notification', {
  state: () => ({
    visible: false,
    type: 'success' as keyof typeof DURATION,
    message: '',
    // a new message resets the previous timer, so it doesn't close early
    hideTimer: undefined as ReturnType<typeof setTimeout> | undefined,
  }),
  actions: {
    show(type: keyof typeof DURATION, message: string) {
      clearTimeout(this.hideTimer)
      this.type = type
      this.message = message
      this.visible = true
      this.hideTimer = setTimeout(() => this.hide(), DURATION[type])
    },

    success(message: string) {
      this.show('success', message)
    },

    error(message: string) {
      this.show('error', message)
    },

    hide() {
      clearTimeout(this.hideTimer)
      this.visible = false
    },
  },
})