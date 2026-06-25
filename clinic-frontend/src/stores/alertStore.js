import { defineStore } from 'pinia'

let nextId = 1

export const useAlertStore = defineStore('alert', {
  state: () => ({ items: [] }),
  actions: {
    push(type, message, timeout = 4000) {
      const id = nextId++
      this.items.push({ id, type, message })
      setTimeout(() => this.dismiss(id), timeout)
    },
    success(msg, t) { this.push('success', msg, t) },
    error(msg, t)   { this.push('error', msg, t) },
    info(msg, t)    { this.push('info', msg, t) },
    warning(msg, t) { this.push('warning', msg, t) },
    dismiss(id)     { this.items = this.items.filter(i => i.id !== id) }
  }
})
