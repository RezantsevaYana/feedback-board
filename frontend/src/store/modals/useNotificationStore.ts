import { defineStore } from 'pinia'

const DURATION = {
  success: 3500,
  error: 5000,
}
// a message with an action (Отменить) stays longer, so there's time to press it
const ACTION_DURATION = 6000

export interface NotificationAction {
  label: string
  run: () => unknown
}

// One short message at the bottom of the screen (rendered in App.vue), optionally with one action.
export const useNotificationStore = defineStore('notification', {
  state: () => ({
    visible: false,
    type: 'success' as keyof typeof DURATION,
    message: '',
    action: null as NotificationAction | null,
    // a new message resets the previous timer, so it doesn't close early
    hideTimer: undefined as ReturnType<typeof setTimeout> | undefined,
  }),
  actions: {
    show(type: keyof typeof DURATION, message: string, action: NotificationAction | null = null) {
      clearTimeout(this.hideTimer)
      this.type = type
      this.message = message
      this.action = action
      this.visible = true
      this.hideTimer = setTimeout(() => this.hide(), action ? ACTION_DURATION : DURATION[type])
    },

    success(message: string, action?: NotificationAction) {
      this.show('success', message, action)
    },

    error(message: string) {
      this.show('error', message)
    },

    // the action button: run it once and close the message
    runAction() {
      const action = this.action
      this.hide()
      return action?.run()
    },

    hide() {
      clearTimeout(this.hideTimer)
      this.visible = false
      this.action = null
    },
  },
})