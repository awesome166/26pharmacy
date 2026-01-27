<template>
    <div>

        <Head title="Sales History" />

        <AppLayout :breadcrumbs="breadcrumbs">

            <div class="audit-dashboard min-h-screen font-sans text-gray-800 dark:bg-gray-900 dark:text-gray-100">
                <!-- Header bg-gradient-to-r from-indigo-500 to-purple-600 text-white -->
                <header class=" p-6 shadow-lg">
                    <div class="container mx-auto">
                        <div class="flex flex-col md:flex-row justify-between items-center mb-6">
                            <div class="flex items-center space-x-3 mb-4 md:mb-0">
                                <i class="fas fa-shield-alt text-3xl"></i>
                                <div>
                                    <h1 class="text-2xl font-bold dark:text-white">Audit Trail Analytics</h1>
                                    <p class="text-blue-800 dark:text-blue-400 text-sm opacity-90">Compliance Monitoring
                                        & Forensic
                                        Analysis
                                        Dashboard</p>
                                </div>
                            </div>
                            <div class="text-right text-xs opacity-75">
                                <p>Last Updated: {{ currentTime }}</p>
                                <p>Source: System Audit Log</p>
                            </div>
                        </div>

                        <!-- Stat Cards -->
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-4">
                            <div v-if="stats" v-for="(stat, key) in stats.counts" :key="key"
                                class="bg-black/10 dark:bg-white/5 backdrop-blur-sm rounded-lg p-4 hover:bg-black/20 dark:hover:bg-white/10 transition-all duration-300 transform hover:-translate-y-1 border border-black/10 dark:border-white/10">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <p
                                            class="text-blue-900 dark:text-blue-300 text-xs uppercase tracking-wider font-semibold">
                                            {{
                                                formatStatLabel(key) }}</p>
                                        <h3 class="text-3xl font-bold mt-1 text-blue-900 dark:text-white">{{
                                            formatNumber(stat) }}</h3>
                                    </div>
                                    <i :class="getStatIcon(key)" class="text-2xl opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </header>

                <!-- Navigation Tabs -->
                <div class="bg-white dark:bg-gray-800 shadow sticky top-0 z-20 dark:border-b dark:border-gray-700 mx-6">
                    <div class="container mx-auto">
                        <nav class="flex overflow-x-auto">
                            <button v-for="tab in tabs" :key="tab.id" @click="activeTab = tab.id"
                                :class="['flex items-center space-x-2 px-6 py-4 border-b-2 font-medium transition-colors whitespace-nowrap',
                                    activeTab === tab.id ? 'border-purple-600 text-purple-600 bg-purple-50 dark:bg-purple-900/20 dark:text-purple-400' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700']">
                                <i :class="tab.icon"></i>
                                <span>{{ tab.name }}</span>
                            </button>
                        </nav>
                    </div>
                </div>

                <!-- Main Content -->
                <main class="container mx-auto p-4 md:p-6 pb-20">

                    <!-- LOADING STATE -->
                    <div v-if="loading.global" class="flex justify-center items-center h-64">
                        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-purple-600"></div>
                    </div>

                    <transition name="fade" mode="out-in">

                        <!-- TAB 1: DASHBOARD OVERVIEW -->
                        <div v-if="activeTab === 'dashboard' && !loading.global" key="dashboard" class="space-y-6">

                            <!-- Filters -->
                            <div
                                class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 flex flex-wrap gap-4 items-center">
                                <div class="flex items-center space-x-2">
                                    <i class="fas fa-filter text-gray-400"></i>
                                    <span class="font-semibold text-gray-700 dark:text-gray-300">Filters:</span>
                                </div>
                                <select v-model="filters.dashboard.range" @change="fetchDashboardStats"
                                    class="form-select text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md focus:ring-purple-500 focus:border-purple-500">
                                    <option value="7">Last 7 Days</option>
                                    <option value="30">Last 30 Days</option>
                                    <option value="90">Last 90 Days</option>
                                </select>
                                <!-- <select v-model="filters.dashboard.account" @change="fetchDashboardStats"
                                    class="form-select text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md focus:ring-purple-500 focus:border-purple-500">
                                    <option value="">All Accounts</option>
                                    <option value="13">Account 13</option>
                                    <option value="14">Account 14</option>
                                    <option value="15">Account 15</option>
                                </select> -->
                                <button @click="fetchDashboardStats"
                                    class="ml-auto px-4 py-2 bg-indigo-50 text-indigo-600 rounded-md hover:bg-indigo-100 text-sm font-medium transition">
                                    <i class="fas fa-sync-alt mr-1"></i> Refresh
                                </button>
                            </div>

                            <!-- Charts Row -->
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                                <!-- Actions by Type -->
                                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
                                    <h3
                                        class="text-lg font-bold text-gray-700 dark:text-gray-200 mb-4 flex items-center">
                                        <i class="fas fa-chart-pie mr-2 text-indigo-500"></i> Actions Distribution
                                    </h3>
                                    <div class="h-64 relative">
                                        <canvas ref="chartActionsByType"></canvas>
                                    </div>
                                </div>

                                <!-- Context / Entities -->
                                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
                                    <h3
                                        class="text-lg font-bold text-gray-700 dark:text-gray-200 mb-4 flex items-center">
                                        <i class="fas fa-cubes mr-2 text-indigo-500"></i> Entities Modified
                                    </h3>
                                    <div class="h-64 relative">
                                        <canvas ref="chartEntitiesModified"></canvas>
                                    </div>
                                </div>
                            </div>

                            <!-- Line Chart -->
                            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
                                <h3 class="text-lg font-bold text-gray-700 dark:text-gray-200 mb-4 flex items-center">
                                    <i class="fas fa-chart-line mr-2 text-indigo-500"></i> Activity Volume
                                </h3>
                                <div class="h-72 relative">
                                    <canvas ref="chartActivityOverTime"></canvas>
                                </div>
                            </div>

                            <!-- Leaderboard -->
                            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
                                <h3 class="text-lg font-bold text-gray-700 dark:text-gray-200 mb-4 flex items-center">
                                    <i class="fas fa-user-shield mr-2 text-indigo-500"></i> User Leaderboard
                                </h3>
                                <div class="overflow-x-auto">
                                    <table class="w-full text-left border-collapse">
                                        <thead>
                                            <tr
                                                class="text-xs font-semibold tracking-wide text-gray-500 dark:text-gray-400 uppercase border-b dark:border-gray-700 bg-gray-50 dark:bg-gray-700">
                                                <th class="px-4 py-3">User</th>
                                                <th class="px-4 py-3">Total Actions</th>
                                                <th class="px-4 py-3">Last Activity</th>
                                                <th class="px-4 py-3 text-center">Trend</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                            <tr v-for="(user, index) in stats.leaderboard" :key="user.actor_user_id"
                                                class="hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                                                <td class="px-4 py-3 font-medium text-gray-900 dark:text-gray-100">
                                                    <div class="flex items-center space-x-3">
                                                        <div
                                                            class="flex-shrink-0 w-8 h-8 rounded-full bg-indigo-100 dark:bg-indigo-900 flex items-center justify-center text-indigo-600 dark:text-indigo-300 font-bold text-xs">
                                                            {{ index + 1 }}
                                                        </div>
                                                        <span>{{ user.user_name || 'User ' + user.actor_user_id
                                                        }}</span>
                                                    </div>
                                                </td>
                                                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{
                                                    user.action_count }}</td>
                                                <td class="px-4 py-3 text-gray-500 dark:text-gray-400 text-sm">{{
                                                    formatDate(user.last_activity) }}
                                                </td>
                                                <td class="px-4 py-3 text-center">
                                                    <!-- Mock trend -->
                                                    <span class="text-green-500 text-xs"><i
                                                            class="fas fa-arrow-up"></i></span>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- TAB 2: USER TIMELINE -->
                        <div v-else-if="activeTab === 'timeline'" key="timeline" class="space-y-6">

                            <!-- Header & Controls -->
                            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
                                <h2 class="text-xl font-bold text-gray-800 dark:text-white mb-6 flex items-center">
                                    <i class="fas fa-users-cog mr-2 text-gray-600 dark:text-gray-400"></i> User Action
                                    Timeline
                                </h2>

                                <div class="flex flex-wrap gap-4 items-end border-b pb-6 mb-6">
                                    <div class="w-48">
                                        <label
                                            class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase mb-1">Select
                                            User</label>
                                        <Select v-model="filters.timeline.user" :defaultValue="filters.timeline.user">
                                            <SelectTrigger>
                                                <SelectValue placeholder="All Users" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="all">All Users</SelectItem>
                                                <SelectItem v-for="user in users" :key="user.id"
                                                    :value="String(user.id)">
                                                    {{ user.name }}
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div class="w-40">
                                        <label
                                            class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase mb-1">Action
                                            Type</label>
                                        <Select v-model="filters.timeline.action"
                                            :defaultValue="filters.timeline.action">
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="all">All Actions</SelectItem>
                                                <SelectItem value="created">Created</SelectItem>
                                                <SelectItem value="updated">Updated</SelectItem>
                                                <SelectItem value="deleted">Deleted</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <div>
                                            <label
                                                class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase mb-1">Start
                                                Date</label>
                                            <Input v-model="filters.timeline.start" type="date" class="w-full" />
                                        </div>
                                        <div>
                                            <label
                                                class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase mb-1">End
                                                Date</label>
                                            <Input v-model="filters.timeline.end" type="date" class="w-full" />
                                        </div>
                                    </div>
                                    <Button @click="fetchUserTimeline"
                                        class="ml-auto bg-indigo-600 hover:bg-indigo-700">
                                        Load Timeline
                                    </Button>
                                </div>

                                <!-- User Profile Card (Mock Data for Visuals) -->
                                <div
                                    class="bg-gray-50 dark:bg-gray-700/40 rounded-xl p-6 mb-8 border border-gray-100 dark:border-gray-700">
                                    <h3 class="font-bold text-gray-800 dark:text-gray-100 mb-3 flex items-center">
                                        <i class="fas fa-user-circle text-gray-400 mr-2"></i> {{
                                            selectedUserName }} Profile | User ID: {{ selectedUserId }}
                                    </h3>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm" v-if="userProfileStats">
                                        <p><span class="font-bold text-gray-700 dark:text-gray-300">Activity
                                                Pattern:</span> {{
                                                    userProfileStats.pattern || 'N/A' }}</p>
                                        <p><span class="font-bold text-gray-700 dark:text-gray-300">Primary
                                                Actions:</span>
                                            <span v-for="(pct, act) in userProfileStats.actions" :key="act"
                                                class="capitalize">{{ act }} ({{ pct }}%) </span>
                                        </p>
                                        <p><span class="font-bold text-gray-700 dark:text-gray-300">Preferred
                                                Entities:</span>
                                            <span v-for="(pct, ent) in userProfileStats.entities" :key="ent"
                                                class="capitalize">{{ ent }} ({{ pct }}%) </span>
                                        </p>
                                        <p><span class="font-bold text-gray-700 dark:text-gray-300">IP Addresses
                                                Used:</span> {{
                                                    userProfileStats.unique_ips }} different IPs detected</p>
                                    </div>
                                </div>

                                <h3 class="font-bold text-lg text-gray-800 dark:text-gray-200 mb-4">Recent Actions</h3>

                                <div v-if="loading.timeline" class="text-center py-12">
                                    <div
                                        class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600">
                                    </div>
                                </div>

                                <!-- Detailed Timeline -->
                                <div v-else
                                    class="relative border-l-2 border-slate-200 dark:border-gray-700 ml-4 space-y-8 pl-8 py-2">
                                    <div v-for="event in timelineData.data" :key="event.id" class="relative">
                                        <!-- Icon Marker -->
                                        <div class="absolute -left-[45px] top-0 h-9 w-9 rounded-full flex items-center justify-center text-white shadow-sm border-4 border-white"
                                            :class="event.action === 'deleted' ? 'bg-red-500' : (event.action === 'created' ? 'bg-green-500' : 'bg-blue-500')">
                                            <i :class="getActionIcon(event.action)" class="text-xs"></i>
                                        </div>

                                        <!-- Content -->
                                        <div class="mb-1">
                                            <div class="flex flex-wrap items-baseline gap-2 mb-1">
                                                <span class="font-bold text-gray-800 dark:text-gray-100 text-base">
                                                    {{ formatActionLabel(event.action) }} {{
                                                        formatEntityName(event.entity_type) }}
                                                </span>
                                                <span
                                                    class="font-mono text-xs bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 px-2 py-0.5 rounded border border-gray-200 dark:border-gray-600"
                                                    :title="event.entity_id">
                                                    {{ event.entity_name || event.entity_id }}
                                                </span>
                                            </div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400 mb-3">
                                                {{ formatDate(event.timestamp) }} • {{ event.account_name || 'Account '
                                                    + (event.account_id || 'N/A') }}
                                            </div>

                                            <!-- Details Box -->
                                            <div
                                                class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4 border border-gray-100 dark:border-gray-700 text-sm">
                                                <span>{{ event.entity_type }}</span> <br>
                                                <span>ID: {{ event.id }}</span> <br>
                                                <div v-if="event.new_values || event.old_values"
                                                    class="mb-2 font-mono text-gray-700 dark:text-gray-300 break-words">
                                                    <span
                                                        class="font-bold text-gray-500 dark:text-gray-400">Changes:</span>
                                                    {{ truncate(JSON.stringify(event.new_values || event.changes || {}),
                                                        150) }}
                                                </div>
                                                <div
                                                    class="flex flex-wrap gap-x-6 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
                                                    <span><span
                                                            class="font-bold text-gray-400 dark:text-gray-500">IP:</span>
                                                        {{
                                                            getMetadata(event.metadata, 'ip') }}</span>
                                                    <span><span class="font-bold text-gray-400 dark:text-gray-500">User
                                                            Agent:</span> {{
                                                                getMetadata(event.metadata, 'user_agent') }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div v-if="timelineData.data && timelineData.data.length === 0"
                                        class="text-gray-500 text-sm">
                                        No actions found for this user in the selected period.
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- TAB 3: ENTITY HISTORY -->
                        <div v-else-if="activeTab === 'entity'" key="entity" class="space-y-6">
                            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
                                <h3 class="text-lg font-bold text-gray-700 mb-6 font-bold dark:text-gray-200">Entity
                                    Lifecycle Search</h3>
                                <div class="flex flex-wrap gap-4 mb-8">
                                    <div class="w-32">
                                        <Select v-model="filters.entity.type" defaultValue="inventory">
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="inventory">Inventory</SelectItem>
                                                <SelectItem value="batch">Batch</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <!-- Inventory Selector -->
                                    <div class="flex-1" v-if="filters.entity.type === 'inventory'">
                                        <Select v-model="filters.entity.id">
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select Item..." />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem v-for="inv in inventories" :key="inv.id"
                                                    :value="String(inv.id)">
                                                    {{ inv.label }}
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>

                                    <!-- Batch Selector -->
                                    <div class="flex-1" v-else-if="filters.entity.type === 'batch'">
                                        <Select v-model="filters.entity.id">
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select Batch..." />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem v-for="b in batches" :key="b.id" :value="String(b.id)">
                                                    {{ b.label }}
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>

                                    <!-- Fallback Text Input (if other types re-enabled) -->
                                    <!-- Fallback Text Input (if other types re-enabled) -->
                                    <Input v-else v-model="filters.entity.id" type="text" placeholder="Entity ID..."
                                        class="flex-1" />

                                    <Button @click="fetchEntityHistory"
                                        class="bg-indigo-600 hover:bg-indigo-700">Search</Button>
                                </div>

                                <div v-if="entityHistory.length > 0">
                                    <div class="flex justify-between items-end mb-4">
                                        <h4 class="font-bold text-gray-800 dark:text-gray-100">History Log ({{
                                            entityHistory.length }}
                                            entries)</h4>
                                    </div>
                                    <div class="rounded-md border dark:border-gray-700 overflow-hidden">
                                        <Table>
                                            <TableHeader class="bg-gray-50 dark:bg-gray-700">
                                                <TableRow>
                                                    <TableHead>Time</TableHead>
                                                    <TableHead>Action</TableHead>
                                                    <TableHead>User</TableHead>
                                                    <TableHead class="text-right">Details</TableHead>
                                                </TableRow>
                                            </TableHeader>
                                            <TableBody>
                                                <TableRow v-for="log in entityHistory" :key="log.id"
                                                    :class="log.is_context ? 'bg-indigo-50/50 dark:bg-indigo-900/20' : 'hover:bg-gray-50 dark:hover:bg-gray-700'">
                                                    <TableCell class="font-medium whitespace-nowrap">
                                                        {{ formatDate(log.timestamp) }}
                                                        <div v-if="log.is_context"
                                                            class="text-[10px] text-indigo-600 dark:text-indigo-400 font-bold uppercase mt-1">
                                                            <i class="fas fa-link mr-1"></i> {{ log.context_label }}
                                                        </div>
                                                    </TableCell>
                                                    <TableCell>
                                                        <span :class="getActionBadgeClass(log.action)"
                                                            class="px-2 py-0.5 rounded text-xs font-bold uppercase">{{
                                                                log.action }}</span>
                                                    </TableCell>
                                                    <TableCell>{{ log.user_name || 'User ' + (log.actor_user_id ||
                                                        'System') }}</TableCell>
                                                    <TableCell class="text-right">
                                                        <Button variant="ghost" size="sm" @click="openDiffModal(log)"
                                                            class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300 h-auto p-0 font-medium text-xs">
                                                            View Changes <i class="fas fa-chevron-right ml-1"></i>
                                                        </Button>
                                                    </TableCell>
                                                </TableRow>
                                            </TableBody>
                                        </Table>
                                    </div>
                                </div>
                                <div v-else-if="hasSearchedEntity" class="text-center py-10 text-gray-500">
                                    No history found for this entity.
                                </div>
                            </div>
                        </div>

                        <!-- TAB 4: DATA TABLE -->
                        <div v-else-if="activeTab === 'table'" key="table" class="space-y-6">
                            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
                                <div class="flex justify-between items-center mb-6">
                                    <h3 class="text-lg font-bold text-gray-700 dark:text-gray-200">Raw Audit Logs</h3>
                                    <Button variant="outline"
                                        class="text-green-600 dark:text-green-400 border-green-200 dark:border-green-900 hover:bg-green-50 dark:hover:bg-green-900/20">
                                        <i class="fas fa-file-csv mr-2"></i> Export CSV
                                    </Button>
                                </div>
                                <div class="rounded-md border dark:border-gray-700 overflow-hidden">
                                    <Table>
                                        <TableHeader class="bg-gray-100 dark:bg-gray-700">
                                            <TableRow>
                                                <TableHead>Timestamp</TableHead>
                                                <TableHead>Action</TableHead>
                                                <TableHead>Entity</TableHead>
                                                <TableHead>ID</TableHead>
                                                <TableHead>User</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            <TableRow v-for="row in history.data" :key="row.id"
                                                class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                                <TableCell>{{ formatDate(row.timestamp) }}</TableCell>
                                                <TableCell>
                                                    <span :class="getActionTextClass(row.action)"
                                                        class="font-bold uppercase text-xs">{{ row.action }}</span>
                                                </TableCell>
                                                <TableCell class="capitalize">{{ row.entity_type }}</TableCell>
                                                <TableCell class="font-mono text-xs text-gray-500 dark:text-gray-400"
                                                    :title="row.entity_id">
                                                    {{ row.entity_name || row.entity_id }}
                                                </TableCell>
                                                <TableCell>{{ row.user_name || 'User ' + row.actor_user_id }}
                                                </TableCell>
                                            </TableRow>
                                        </TableBody>
                                    </Table>
                                </div>

                                <!-- Pagination (Shadcn) -->
                                <div class="mt-4 flex justify-end">
                                    <Pagination v-if="history.last_page > 1" :total="history.total" :sibling-count="1"
                                        show-edges :default-page="history.current_page">
                                        <PaginationContent>
                                            <PaginationPrevious @click="fetchTableData(history.current_page - 1)"
                                                :disabled="!history.prev_page_url" class="cursor-pointer" />

                                            <template
                                                v-for="page in getPaginationRange(history.current_page, history.last_page)">
                                                <PaginationEllipsis v-if="page === '...'"
                                                    :key="'ellipsis-' + Math.random()" />
                                                <PaginationItem v-else :key="page" :value="page"
                                                    :isActive="history.current_page === page"
                                                    @click="fetchTableData(page)" class="cursor-pointer">
                                                    {{ page }}
                                                </PaginationItem>
                                            </template>

                                            <PaginationNext @click="fetchTableData(history.current_page + 1)"
                                                :disabled="!history.next_page_url" class="cursor-pointer" />
                                        </PaginationContent>
                                    </Pagination>
                                </div>
                            </div>
                        </div>

                        <!-- TAB 5: STORY MODE was removed -->
                        <!-- TAB 5: STORY MODE was removed -->

                    </transition>
                </main>
            </div>
        </AppLayout>
    </div>

    <!-- Diff Viewer Modal -->
    <div v-if="showDiff"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm animate-fade-in">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-4xl max-h-[90vh] flex flex-col overflow-hidden">
            <!-- Header -->
            <div class="bg-gray-50 border-b p-4 flex justify-between items-center">
                <div>
                    <h3 class="font-bold text-lg text-gray-800">
                        Audit Details: <span class="uppercase text-indigo-600">{{ selectedLogRaw?.action }}</span>
                    </h3>
                    <p class="text-xs text-gray-500 font-mono mt-1">
                        {{ formatDate(selectedLogRaw?.timestamp) }} • User: {{ selectedLogRaw?.user_name }}
                    </p>
                </div>
                <button @click="closeDiffModal" class="text-gray-400 hover:text-gray-600 transition">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <!-- Content -->
            <div class="p-6 overflow-y-auto flex-1">
                <div v-if="diffData.length === 0" class="text-center py-12 text-gray-500 dark:text-gray-400">
                    <i class="fas fa-check-circle text-4xl text-green-200 mb-3"></i>
                    <p>No field changes detected.</p>
                    <p class="text-xs mt-1 opacity-75">(Action might be a touch/verify or data is unchanged)</p>
                </div>

                <div v-else>
                    <table class="w-full text-sm border-collapse">
                        <thead>
                            <tr
                                class="bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 uppercase text-xs border-b dark:border-gray-600">
                                <th class="px-4 py-3 text-left w-1/4">Field</th>
                                <th class="px-4 py-3 text-left w-1/3">Old Value</th>
                                <th class="px-4 py-3 text-left w-1/3">New Value</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(change, idx) in diffData" :key="idx"
                                class="border-b dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700">
                                <td
                                    class="px-4 py-3 font-mono font-bold text-gray-700 dark:text-gray-300 bg-gray-50/50 dark:bg-gray-700/50">
                                    {{
                                        change.field }}</td>
                                <td
                                    class="px-4 py-3 font-mono text-red-600 dark:text-red-400 break-all bg-red-50/20 dark:bg-red-900/20">
                                    <span v-if="!change.is_new">{{ change.old }}</span>
                                    <span v-else class="text-gray-300 dark:text-gray-500 italic text-xs">(null)</span>
                                </td>
                                <td
                                    class="px-4 py-3 font-mono text-green-600 dark:text-green-400 break-all bg-green-50/20 dark:bg-green-900/20">
                                    {{
                                        change.new }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Metadata Footer -->
                <div
                    class="mt-8 pt-4 border-t dark:border-gray-700 grid grid-cols-2 gap-4 text-xs text-gray-500 dark:text-gray-400">
                    <div>
                        <span class="font-bold block text-gray-700 dark:text-gray-300 mb-1">Context Info:</span>
                        <div class="grid grid-cols-[80px_1fr] gap-1">
                            <span>Entity ID:</span> <span class="font-mono">{{ selectedLogRaw?.entity_id }}</span>
                            <span>IP Addr:</span> <span>{{ getMetadata(selectedLogRaw?.metadata, 'ip') }}</span>
                            <span>Agent:</span> <span class="truncate"
                                :title="getMetadata(selectedLogRaw?.metadata, 'user_agent')">{{
                                    getMetadata(selectedLogRaw?.metadata, 'user_agent') }}</span>
                        </div>
                    </div>
                    <div>
                        <span class="font-bold block text-gray-700 dark:text-gray-300 mb-1">Raw Metadata:</span>
                        <pre class="bg-gray-100 dark:bg-gray-900 p-2 rounded text-[10px] overflow-x-auto max-h-24">{{
                            selectedLogRaw?.metadata }}</pre>
                    </div>
                </div>
            </div>

            <div class="p-4 border-t dark:border-gray-700 bg-gray-50 dark:bg-gray-700 flex justify-end">
                <button @click="closeDiffModal"
                    class="px-4 py-2 bg-white dark:bg-gray-600 border border-gray-300 dark:border-gray-500 rounded text-gray-700 dark:text-gray-100 hover:bg-gray-100 dark:hover:bg-gray-500 text-sm font-bold">
                    Close Details
                </button>
            </div>
        </div>
    </div>

</template>

<script setup>
import { ref, onMounted, onUnmounted, nextTick, watch, computed } from 'vue';
import Chart from 'chart.js/auto';

import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import axios from 'axios';

// Shadcn UI Components
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCaption,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    Pagination,
    PaginationContent,
    PaginationEllipsis,
    PaginationItem,
    PaginationNext,
    PaginationPrevious,
} from '@/components/ui/pagination';

const props = defineProps({
    history: Object, // Initial paginated list
    users: { type: Array, default: () => [] },
    inventories: { type: Array, default: () => [] },
    batches: { type: Array, default: () => [] },
    filters: Object
});



// Breadcrumbs
const breadcrumbs = [
    {
        title: 'Audit Trail',
        href: '/app/audit-trail',
    },
];

// State
const activeTab = ref('dashboard');
const currentTime = ref(new Date().toISOString().slice(0, 16).replace('T', ' '));
const tabs = [
    { id: 'dashboard', name: 'Dashboard', icon: 'fas fa-tachometer-alt' },
    { id: 'timeline', name: 'User Timeline', icon: 'fas fa-stream' },
    { id: 'entity', name: 'Entity History', icon: 'fas fa-cube' },
    { id: 'table', name: 'Data Table', icon: 'fas fa-table' },
];

const loading = ref({ global: false, timeline: false, table: false });
const filters = ref({
    dashboard: { range: '30', account: '' },
    timeline: { user: props.users?.[0]?.id || '', action: 'all', start: '', end: '' },
    entity: { type: 'drug', id: '' },
    table: { search: '', entity_type: '', action: '', account: '' }
});

const stats = ref({
    counts: { total_records: 0, active_users: 0, entity_types: 0, actions_today: 0 },
    leaderboard: []
});

const timelineData = ref({ data: [] });
const userProfileStats = ref(null);
const entityHistory = ref([]);
const hasSearchedEntity = ref(false);

const selectedUserName = computed(() => {
    const u = props.users?.find(u => u.id === filters.value.timeline.user);
    return u ? u.name : (filters.value.timeline.user ? 'User ' + filters.value.timeline.user : 'User');
});

const selectedUserId = computed(() => {
    const u = props.users?.find(u => u.id === filters.value.timeline.user);
    return u ? u.id : (filters.value.timeline.user ? filters.value.timeline.user : '');
})

// Refs for charts
const chartActionsByType = ref(null);
const chartEntitiesModified = ref(null);

const chartActivityOverTime = ref(null);
const isDark = ref(false); // Will be set on mount
let themeObserver = null;
const showDiff = ref(false);
const selectedLogRaw = ref(null);
const diffData = ref([]);

const openDiffModal = (log) => {
    selectedLogRaw.value = log;

    // Calculate Diff
    const oldV = log.old_values || {}; // Should be object due to backend cast
    const newV = log.new_values || {}; // Should be object due to backend cast

    // Normalize if they came as strings (safeguard)
    const oldObj = (typeof oldV === 'string') ? JSON.parse(oldV || '{}') : oldV;
    const newObj = (typeof newV === 'string') ? JSON.parse(newV || '{}') : newV;

    const allKeys = new Set([...Object.keys(oldObj), ...Object.keys(newObj)]);
    const diff = [];

    allKeys.forEach(key => {
        const valOld = oldObj[key];
        const valNew = newObj[key];

        // Simple comparison
        if (JSON.stringify(valOld) !== JSON.stringify(valNew)) {
            diff.push({
                field: key,
                old: valOld === undefined ? '(null)' : valOld,
                new: valNew === undefined ? '(null)' : valNew,
                is_new: valOld === undefined
            });
        }
    });

    diffData.value = diff;
    showDiff.value = true;
};
const closeDiffModal = () => showDiff.value = false;

let charts = {};
const dashboardChartData = ref(null);

// Helpers
const formatDate = (str) => {
    if (!str) return '-';
    return str.replace('T', ' ').slice(0, 19);
};
const truncate = (str, len) => str.length > len ? str.substring(0, len) + '...' : str;
const formatNumber = (num) => new Intl.NumberFormat().format(num);
const formatStatLabel = (key) => key.replace(/_/g, ' ');
const formatEntityName = (key) => key.charAt(0).toUpperCase() + key.slice(1);

const getPaginationRange = (current, last) => {
    const delta = 2;
    const range = [];
    for (let i = Math.max(2, current - delta); i <= Math.min(last - 1, current + delta); i++) {
        range.push(i);
    }
    if (current - delta > 2) range.unshift('...');
    if (current + delta < last - 1) range.push('...');
    range.unshift(1);
    if (last > 1) range.push(last);
    return range;
};

const getStatIcon = (key) => {
    const map = { total_records: 'fas fa-database', active_users: 'fas fa-users', entity_types: 'fas fa-layer-group', actions_today: 'fas fa-bolt' };
    return map[key] || 'fas fa-info';
};

const getMetadata = (data, key) => {
    try {
        const meta = (typeof data === 'string') ? JSON.parse(data || '{}') : (data || {});
        if (key === 'ip') return meta.ip || '127.0.0.1'; // Fallback
        if (key === 'user_agent') return (meta.user_agent || 'Mozilla/5.0...').substring(0, 30) + '...';
        return '';
    } catch (e) { return '-'; }
};
const formatActionLabel = (action) => action.charAt(0).toUpperCase() + action.slice(1) + (action.endsWith('e') ? 'd' : 'ed'); // Simple logic

const getActionColorClass = (action) => {
    switch (action) {
        case 'created': return 'bg-green-500 border-green-100';
        case 'updated': return 'bg-blue-500 border-blue-100';
        case 'deleted': return 'bg-red-500 border-red-100';
        case 'verified': return 'bg-yellow-500 border-yellow-100';
        default: return 'bg-gray-400';
    }
};
const getActionBadgeClass = (action) => {
    switch (action) {
        case 'created': return 'bg-green-100 text-green-700';
        case 'updated': return 'bg-blue-100 text-blue-700';
        case 'deleted': return 'bg-red-100 text-red-700';
        case 'verified': return 'bg-yellow-100 text-yellow-700';
        default: return 'bg-gray-100 text-gray-700';
    }
};
const getActionTextClass = (action) => {
    switch (action) {
        case 'created': return 'text-green-600';
        case 'updated': return 'text-blue-600';
        case 'deleted': return 'text-red-600';
        case 'verified': return 'text-yellow-600';
        default: return 'text-gray-600';
    }
};
const getActionIcon = (action) => {
    switch (action) {
        case 'created': return 'fas fa-plus';
        case 'updated': return 'fas fa-pen';
        case 'deleted': return 'fas fa-trash';
        case 'verified': return 'fas fa-check';
        default: return 'fas fa-circle';
    }
};

// API Calls
const fetchDashboardStats = async () => {
    loading.value.global = true; // Use global loading only for init or big refresh
    try {
        const res = await axios.get('/app/audit-trail', {
            params: {
                action: 'stats',
                account_id: filters.value.dashboard.account
            }
        });

        // Update stats
        stats.value.counts = res.data.counts;
        stats.value.leaderboard = res.data.leaderboard;
        dashboardChartData.value = res.data.chart_data;
    } catch (e) {
        console.error("Failed to load dashboard stats", e);
    } finally {
        loading.value.global = false;
        // Render charts after DOM update
        await nextTick();
        if (dashboardChartData.value && activeTab.value === 'dashboard') {
            renderCharts(dashboardChartData.value);
        }
    }
};

watch(activeTab, async (newVal) => {
    if (newVal === 'dashboard' && dashboardChartData.value) {
        await nextTick();
        renderCharts(dashboardChartData.value);
    }
});

const fetchUserTimeline = async () => {
    loading.value.timeline = true;
    try {
        const [resTimeline, resStats] = await Promise.all([
            axios.get('/app/audit-trail', {
                params: {
                    action: 'timeline',
                    user_id: filters.value.timeline.user,
                    action_type: filters.value.timeline.action,
                    start_date: filters.value.timeline.start,
                    end_date: filters.value.timeline.end
                }
            }),
            axios.get('/app/audit-trail', { // Fetch stats concurrently
                params: {
                    action: 'user_stats',
                    user_id: filters.value.timeline.user,
                    account_id: filters.value.table.account // Or context account
                }
            })
        ]);

        timelineData.value = resTimeline.data;
        userProfileStats.value = resStats.data;
    } catch (e) {
        console.error(e);
    } finally {
        loading.value.timeline = false;
    }
};



const switchToTimeline = (userId) => {
    activeTab.value = 'timeline';
    filters.value.timeline.user = userId;
    fetchUserTimeline();
};

const fetchEntityHistory = async () => {
    if (!filters.value.entity.id) return;
    try {
        const res = await axios.get('/app/audit-trail', {
            params: {
                action: 'history',
                entity_type: filters.value.entity.type,
                entity_id: filters.value.entity.id
            }
        });
        entityHistory.value = res.data;
        hasSearchedEntity.value = true;
    } catch (e) {
        console.error(e);
        if (e.response && e.response.status === 422) {
            alert(e.response.data.error || 'Validation failed.');
        } else {
            alert('Failed to fetch history. Please check ID and try again.');
        }
    }
};



const fetchTableData = async (page = 1) => {
    loading.value.table = true;
    try {
        const res = await axios.get('/app/audit-trail', {
            params: {
                page: page,
                action: 'history', // Reuse history endpoint but maybe needs more filters support in backend
                entity_type: filters.value.table.entity_type,
                // Note: The backend getHistory might mostly support entity_id exact match, we might need to enhance it or use a new 'search' param
                // For now, let's assume we pass what we have.
                // If the user searches by ID, we pass it.
                search: filters.value.table.search,
                action_type: filters.value.table.action,
                account_id: filters.value.table.account
            }
        });
        // Handle both simple array or paginated object
        tableData.value = res.data;
    } catch (e) { console.error(e); }
    finally { loading.value.table = false; }
};

const exportCSV = () => {
    // Build query params from current table filters
    const params = new URLSearchParams({
        action: 'export', // Backend should handle this or simply ignored if not impl on same route
        entity_type: filters.value.table.entity_type,
        search: filters.value.table.search,
        action_type: filters.value.table.action,
        account_id: filters.value.table.account
    });

    // Direct window open to trigger download
    window.location.href = `/app/audit-trail?${params.toString()}`;
};

// Charts Logic
const renderCharts = (data) => {
    // Destroy old if exists
    if (charts.type) charts.type.destroy();
    if (charts.entity) charts.entity.destroy();
    if (charts.line) charts.line.destroy();

    const textColor = isDark.value ? '#e2e8f0' : '#4a5568'; // gray-200 : gray-700
    const gridColor = isDark.value ? '#4a5568' : '#e2e8f0'; // gray-700 : gray-200

    // 1. Actions by Type (Doughnut)
    if (chartActionsByType.value) {
        charts.type = new Chart(chartActionsByType.value, {
            type: 'doughnut',
            data: {
                labels: data.actions_by_type.map(d => d.action),
                datasets: [{
                    data: data.actions_by_type.map(d => d.count),
                    backgroundColor: ['#48bb78', '#4299e1', '#f56565', '#ecc94b'],
                    borderColor: isDark.value ? '#1f2937' : '#ffffff',
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { labels: { color: textColor } }
                }
            }
        });
    }

    // 2. Entities Modified (Bar)
    if (chartEntitiesModified.value) {
        charts.entity = new Chart(chartEntitiesModified.value, {
            type: 'bar',
            data: {
                labels: data.entities_modified.map(d => d.entity_type),
                datasets: [{
                    label: 'Count',
                    data: data.entities_modified.map(d => d.count),
                    backgroundColor: '#667eea',
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { color: textColor },
                        grid: { color: gridColor }
                    },
                    x: {
                        ticks: { color: textColor },
                        grid: { color: gridColor }
                    }
                },
                plugins: {
                    legend: { labels: { color: textColor } }
                }
            }
        });
    }

    // 3. Activity Over Time (Line)
    if (chartActivityOverTime.value) {
        charts.line = new Chart(chartActivityOverTime.value, {
            type: 'line',
            data: {
                labels: data.activity_over_time.map(d => d.date),
                datasets: [{
                    label: 'Records',
                    data: data.activity_over_time.map(d => d.count),
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { color: textColor },
                        grid: { color: gridColor }
                    },
                    x: {
                        ticks: { color: textColor },
                        grid: { color: gridColor }
                    }
                },
                plugins: {
                    legend: { labels: { color: textColor } }
                }
            }
        });
    }
}

// Init
onMounted(() => {
    isDark.value = document.documentElement.classList.contains('dark');

    // Watch for class changes on html element
    themeObserver = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            if (mutation.attributeName === 'class') {
                const newIsDark = document.documentElement.classList.contains('dark');
                if (isDark.value !== newIsDark) {
                    isDark.value = newIsDark;
                    if (dashboardChartData.value && activeTab.value === 'dashboard') {
                        renderCharts(dashboardChartData.value);
                    }
                }
            }
        });
    });

    themeObserver.observe(document.documentElement, { attributes: true });

    fetchDashboardStats();
});

onUnmounted(() => {
    if (themeObserver) themeObserver.disconnect();
});

</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
