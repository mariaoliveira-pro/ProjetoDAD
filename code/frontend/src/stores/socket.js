import { defineStore } from 'pinia'
import { inject } from 'vue'

export const useSocketStore = defineStore('socket', () => {
  const socket = inject('socket')

  const handleConnection = () => {
    socket.on('connect', () => {
      console.log(`[Socket] Connected -- ${socket.id}`)
    })
    socket.on('disconnect', () => {
      console.log(`[Socket] Disconnected -- ${socket.id}`)
    })
  }

  return {
    handleConnection,
  }
})
