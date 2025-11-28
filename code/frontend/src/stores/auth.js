import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { useAPIStore } from './api'

export const useAuthStore = defineStore('auth', () => {
  const apiStore = useAPIStore()

  const currentUser = ref(undefined)

  const isLoggedIn = computed(() => {
    return currentUser.value !== undefined
  })

  const currentUserID = computed(() => {
    return currentUser.value?.id
  })

  const login = async (credentials) => {
    await apiStore.postLogin(credentials)
    const response = await apiStore.getAuthUser()
    currentUser.value = response.data
    return response.data
  }

  const logout = async () => {
    await apiStore.postLogout()
    currentUser.value = undefined
  }

  return {
    currentUser,
    isLoggedIn,
    currentUserID,
    login,
    logout,
  }
})
