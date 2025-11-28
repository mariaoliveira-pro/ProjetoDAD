<template>
    <div class="max-w-2xl mx-auto py-8">
        <h2 class="text-2xl font-bold text-gray-900 mb-6">Laravel Tester</h2>

        <div class="space-y-4 mt-10">
            <div class="flex gap-4">
                <Select v-model="selectedType" @update:modelValue="handleFiltersChange">
                    <SelectTrigger class="w-[180px]">
                        <SelectValue placeholder="Select type" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="3">Bisca 3</SelectItem>
                        <SelectItem value="9">Bisca 9</SelectItem>
                    </SelectContent>
                </Select>

                <Select v-model="selectedStatus" @update:modelValue="handleFiltersChange">
                    <SelectTrigger class="w-[180px]">
                        <SelectValue placeholder="Select status" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="Pending">Pending</SelectItem>
                        <SelectItem value="Playing">Playing</SelectItem>
                        <SelectItem value="Ended">Ended</SelectItem>
                        <SelectItem value="Interrupted">Interrupted</SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <div class="rounded-md border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>ID</TableHead>
                            <TableHead>
                                <div class="flex items-center gap-2 cursor-pointer" @click="toggleSort('type')">
                                    Type
                                    <ArrowUpDown class="h-4 w-4" />
                                </div>
                            </TableHead>
                            <TableHead>
                                <div class="flex items-center gap-2 cursor-pointer" @click="toggleSort('status')">
                                    Status
                                    <ArrowUpDown class="h-4 w-4" />
                                </div>
                            </TableHead>
                            <TableHead>Created By</TableHead>
                            <TableHead>Winner</TableHead>
                            <TableHead>
                                <div class="flex items-center gap-2 cursor-pointer" @click="toggleSort('total_time')">
                                    Total Time
                                    <ArrowUpDown class="h-4 w-4" />
                                </div>
                            </TableHead>
                            <TableHead>
                                <div class="flex items-center gap-2 cursor-pointer" @click="toggleSort('created_at')">
                                    Began At
                                    <ArrowUpDown class="h-4 w-4" />
                                </div>
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="game in games" :key="game.id">
                            <TableCell>{{ game.id }}</TableCell>
                            <TableCell>
                                <Badge :variant="game.type === '3' ? 'default' : 'secondary'">
                                    {{ game.type === '3' ? 'Bisca 3' : 'Bisca 9' }}
                                </Badge>
                            </TableCell>
                            <TableCell>
                                <Badge :variant="getStatusVariant(game.status)">
                                    {{ game.status }}
                                </Badge>
                            </TableCell>
                            <TableCell>{{ game.created_by?.name }}</TableCell>
                            <TableCell>{{ game.winner?.name || '-' }}</TableCell>
                            <TableCell>{{ game.total_time ? `${game.total_time}s` : '-' }}</TableCell>
                            <TableCell>{{ formatDate(game.began_at) }}</TableCell>
                        </TableRow>
                        <TableRow v-if="!games?.length">
                            <TableCell colspan="7" class="text-center h-24">No games found</TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <div class="flex justify-center mb-20">
                <Button variant="outline" @click="loadMore" :disabled="loading">
                    <Loader2 v-if="loading" class="mr-2 h-4 w-4 animate-spin" />
                    Load More
                </Button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { Button } from '@/components/ui/button'
import { format } from 'date-fns'
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow
} from '@/components/ui/table'
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue
} from '@/components/ui/select'
import { Badge } from '@/components/ui/badge'
import { ArrowUpDown, Loader2 } from 'lucide-vue-next'
import { useAPIStore } from '@/stores/api';


const apiStore = useAPIStore()
const games = ref([])

const loading = ref(false)
const selectedType = ref('')
const selectedStatus = ref('')
const sortField = ref('began_at')
const sortDirection = ref('desc')


const fetchData = async (resetPagination = false) => {
    loading.value = true

    apiStore.gameQueryParameters.filters = {
        type: selectedType.value,
        status: selectedStatus.value,
        sort_by: sortField.value,
        sort_direction: sortDirection.value
    }

    const response = await apiStore.getGames(resetPagination)
    loading.value = false
    games.value = response.data.data
}

const handleFiltersChange = async () => {
    await fetchData(true)
}

const toggleSort = (field) => {
    if (sortField.value === field) {
        sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc'
    } else {
        sortField.value = field
        sortDirection.value = 'desc'
    }
    handleFiltersChange()
}

const loadMore = async () => {
    loading.value = true
    apiStore.gameQueryParameters.page++
    const response = await apiStore.getGames()
    games.value.push(...response.data.data)
    loading.value = false
}

const getStatusVariant = (status) => {
    const variants = {
        Pending: 'secondary',
        Playing: 'default',
        Ended: 'success',
        Interrupted: 'destructive'
    }
    return variants[status] || 'default'
}



const formatDate = (date) => {
    if (!date) return ''
    return format(new Date(date), 'PPp')
}


onMounted(async () => {
    await fetchData()
})

</script>
