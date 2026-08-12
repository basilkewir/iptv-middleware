<template>
  <AdminLayout>
    <div class="p-6 space-y-6">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-white">Video on Demand</h1>
          <p class="text-gray-400 mt-1">Manage movies, series, and VOD content</p>
        </div>
        <div class="flex gap-3">
          <Link :href="route('admin.vod.import')" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition flex items-center gap-2"><Upload class="w-4 h-4" /> Import</Link>
          <Link :href="route('admin.vod.create')" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg transition flex items-center gap-2"><Plus class="w-4 h-4" /> Add VOD</Link>
        </div>
      </div>

      <!-- Filters -->
      <div class="bg-gray-800 rounded-xl p-4 border border-gray-700">
        <div class="flex flex-wrap gap-4 items-center">
          <div class="flex-1 min-w-[200px]">
            <div class="relative">
              <Search class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
              <input v-model="search" type="text" placeholder="Search movies, series..." class="w-full pl-10 pr-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:border-indigo-500" />
            </div>
          </div>
          <select v-model="filterType" class="px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white">
            <option value="">All Types</option><option value="movie">Movies</option><option value="series">Series</option>
          </select>
          <select v-model="filterCategory" class="px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white">
            <option value="">All Categories</option>
            <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
          </select>
          <select v-model="filterFeatured" class="px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white">
            <option value="">All</option><option value="1">Featured</option><option value="0">Not Featured</option>
          </select>
          <select v-model="filterQuality" class="px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white">
            <option value="">All Qualities</option><option value="4k">4K</option><option value="fhd">FHD</option><option value="hd">HD</option><option value="sd">SD</option><option value="low">Low</option>
          </select>
          <div class="flex gap-1 bg-gray-700 rounded-lg p-1">
            <button @click="viewMode = 'grid'" class="p-2 rounded transition" :class="viewMode === 'grid' ? 'bg-indigo-600 text-white' : 'text-gray-400 hover:text-white'"><LayoutGrid class="w-4 h-4" /></button>
            <button @click="viewMode = 'list'" class="p-2 rounded transition" :class="viewMode === 'list' ? 'bg-indigo-600 text-white' : 'text-gray-400 hover:text-white'"><List class="w-4 h-4" /></button>
          </div>
        </div>
      </div>

      <!-- Grid View -->
      <div v-if="viewMode === 'grid'" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <div v-for="item in filteredVods" :key="item.id" class="bg-gray-800 rounded-xl border border-gray-700 overflow-hidden hover:border-gray-600 transition">
          <div class="aspect-[2/3] bg-gray-700 flex items-center justify-center relative">
            <img v-if="item.poster_url" :src="item.poster_url" :alt="item.title" class="w-full h-full object-cover" />
            <Film v-else class="w-12 h-12 text-gray-500" />
            <QualityBadge v-if="item.quality_level" :quality="item.quality_level" size="sm" class="absolute top-2 right-2" />
            <span v-else class="absolute top-2 right-2 px-2 py-1 text-xs rounded bg-black/60 text-white">{{ item.type }}</span>
            <span v-if="item.is_featured" class="absolute top-2 left-2 px-2 py-1 text-xs rounded bg-yellow-500 text-black font-medium">Featured</span>
            <span v-if="item.rating > 0" class="absolute bottom-2 right-2 px-2 py-1 text-xs rounded bg-black/60 text-yellow-400">★ {{ item.rating }}</span>
          </div>
          <div class="p-3">
            <h3 class="text-white font-semibold text-sm truncate">{{ item.title }}</h3>
            <p class="text-gray-400 text-xs mt-1">{{ item.year || 'N/A' }} · {{ item.duration ? item.duration + 'min' : '--' }}</p>
            <div class="flex flex-wrap gap-1 mt-2">
              <span v-for="g in (item.genre || []).slice(0, 2)" :key="g" class="px-1.5 py-0.5 text-xs bg-gray-700 text-gray-300 rounded">{{ g }}</span>
            </div>
            <div class="flex gap-2 mt-3">
              <Link :href="route('admin.vod.edit', item.id)" class="flex-1 px-2 py-1.5 bg-gray-700 hover:bg-gray-600 text-white rounded text-xs transition text-center">Edit</Link>
              <button @click="toggleFeatured(item)" class="px-2 py-1.5 rounded text-xs transition" :class="item.is_featured ? 'bg-yellow-600/20 text-yellow-400 hover:bg-yellow-600/30' : 'bg-gray-700 text-gray-400 hover:bg-gray-600'">
                <Star class="w-3.5 h-3.5" :fill="item.is_featured ? 'currentColor' : 'none'" />
              </button>
              <button @click="confirmDelete(item)" class="px-2 py-1.5 bg-red-600/20 hover:bg-red-600 text-red-400 hover:text-white rounded text-xs transition"><Trash2 class="w-3.5 h-3.5" /></button>
            </div>
          </div>
        </div>
      </div>

      <!-- List View -->
      <div v-else class="bg-gray-800 rounded-xl border border-gray-700 overflow-hidden">
        <table class="w-full">
          <thead><tr class="border-b border-gray-700">
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Title</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Type</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Year</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Rating</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Status</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Actions</th>
          </tr></thead>
          <tbody class="divide-y divide-gray-700">
            <tr v-for="item in filteredVods" :key="item.id" class="hover:bg-gray-700/50">
              <td class="px-4 py-3">
                <div class="flex items-center gap-3">
                  <img v-if="item.poster_url" :src="item.poster_url" class="w-10 h-14 rounded object-cover bg-gray-700" />
                  <div v-else class="w-10 h-14 rounded bg-gray-700 flex items-center justify-center"><Film class="w-5 h-5 text-gray-500" /></div>
                  <div>
                    <p class="text-white font-medium">{{ item.title }}</p>
                    <p class="text-gray-500 text-xs">{{ (item.genre || []).slice(0, 2).join(', ') }}</p>
                  </div>
                </div>
              </td>
              <td class="px-4 py-3"><span class="px-2 py-0.5 text-xs rounded bg-gray-700 text-gray-300 capitalize">{{ item.type }}</span></td>
              <td class="px-4 py-3 text-gray-400 text-sm">{{ item.year || '-' }}</td>
              <td class="px-4 py-3 text-yellow-400 text-sm">★ {{ item.rating || '0' }}</td>
              <td class="px-4 py-3">
                <span class="px-2 py-1 text-xs rounded-full" :class="item.is_active ? 'bg-green-500/20 text-green-400' : 'bg-red-500/20 text-red-400'">
                  {{ item.is_active ? 'Active' : 'Inactive' }}
                </span>
              </td>
              <td class="px-4 py-3">
                <div class="flex items-center gap-1">
                  <Link :href="route('admin.vod.edit', item.id)" class="p-1.5 hover:bg-gray-600 rounded text-gray-400 hover:text-white"><Pencil class="w-4 h-4" /></Link>
                  <button @click="confirmDelete(item)" class="p-1.5 hover:bg-red-600/20 rounded text-gray-400 hover:text-red-400"><Trash2 class="w-4 h-4" /></button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="vods?.links" class="flex items-center justify-between">
        <p class="text-gray-400 text-sm">Showing {{ vods.from }} to {{ vods.to }} of {{ vods.total }} items</p>
        <div class="flex gap-2">
          <Link v-for="page in vods.links" :key="page.label" :href="page.url || '#'" class="px-3 py-1 rounded-lg text-sm"
            :class="page.active ? 'bg-indigo-600 text-white' : 'bg-gray-700 text-gray-400 hover:bg-gray-600'" preserve-scroll>{{ page.label }}</Link>
        </div>
      </div>

      <!-- Delete Modal -->
      <div v-if="deleteTarget" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm">
        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700 w-full max-w-md">
          <h3 class="text-lg font-semibold text-white mb-2">Delete Content</h3>
          <p class="text-gray-400">Delete "<strong class="text-white">{{ deleteTarget.title }}</strong>"?</p>
          <div class="flex justify-end gap-3 mt-6">
            <button @click="deleteTarget = null" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition">Cancel</button>
            <button @click="performDelete" class="px-4 py-2 bg-red-600 hover:bg-red-500 text-white rounded-lg transition">Delete</button>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { route } from '@/Composables/useRoute'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { Search, Plus, Upload, Film, Pencil, Trash2, Star, LayoutGrid, List } from 'lucide-vue-next'
import QualityBadge from '@/Components/QualityBadge.vue'

const props = defineProps({ vods: { type: Object, default: () => ({ data: [], links: [] }) }, categories: { type: Array, default: () => [] } })

const search = ref('')
const filterType = ref('')
const filterCategory = ref('')
const filterFeatured = ref('')
const filterQuality = ref('')
const viewMode = ref('grid')
const filteredVods = computed(() => {
  let items = props.vods?.data || []
  if (search.value) {
    const q = search.value.toLowerCase()
    items = items.filter(i => i.title?.toLowerCase().includes(q) || i.description?.toLowerCase().includes(q))
  }
  if (filterType.value) items = items.filter(i => i.type === filterType.value)
  if (filterCategory.value) items = items.filter(i => (i.categories || []).some(c => c.id == filterCategory.value))
  if (filterFeatured.value !== '') items = items.filter(i => String(Number(i.is_featured)) === filterFeatured.value)
  if (filterQuality.value) items = items.filter(i => i.quality_level === filterQuality.value)
  return items
})
const deleteTarget = ref(null)

const toggleFeatured = (item) => { router.post(route('admin.vod.toggle-featured', item.id), {}, { preserveScroll: true }) }
const confirmDelete = (item) => { deleteTarget.value = item }
const performDelete = () => {
  const id = Number(deleteTarget.value?.id)
  if (!id) { deleteTarget.value = null; return }
  router.delete(`/admin/vod/${id}`, {
    preserveScroll: true,
    onSuccess: () => { deleteTarget.value = null },
    onError: () => { deleteTarget.value = null },
  })
}
</script>
