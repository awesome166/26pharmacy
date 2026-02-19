import { ref, computed } from 'vue';
import axios from 'axios';

// Assuming Echo is available globally or via injection
declare global {
  interface Window {
    Echo: any;
  }
}

export interface OnlineDevice {
  id: string;
  name: string;
  email: string;
  device: string;
  role: string;
  client_id: string;
}

class SyncManagerClient {
  public isOnline = ref(false);
  public isSyncing = ref(false);
  public lastSyncTime = ref<Date | null>(null);
  public syncStatus = ref<'idle' | 'syncing' | 'error' | 'success'>('idle');
  public onlineDevices = ref<OnlineDevice[]>([]);

  private tenantId: string | null = null;
  private channel: any = null;

  constructor() {
    this.checkOnlineStatus();
    window.addEventListener('online', () => this.handleConnectionChange(true));
    window.addEventListener('offline', () => this.handleConnectionChange(false));
  }

  public initialize(tenantId: string) {
    this.tenantId = tenantId;
    this.setupEcho();
    this.triggerInitialSync();
  }

  private handleConnectionChange(online: boolean) {
    this.isOnline.value = online;
    if (online) {
      this.triggerInitialSync();
    }
  }

  private checkOnlineStatus() {
    this.isOnline.value = navigator.onLine;
  }

  private setupEcho() {
    if (!this.tenantId || !window.Echo) return;

    // Cleanup existing channel if re-initializing
    if (this.channel) {
      window.Echo.leave(`sync.tenant.${this.tenantId}`);
    }

    // Join presence channel
    this.channel = window.Echo.join(`sync.tenant.${this.tenantId}`)
      .here((users: OnlineDevice[]) => {
        // console.log('Online Users:', users);
        this.onlineDevices.value = users;
        this.isOnline.value = true;
      })
      .joining((user: OnlineDevice) => {
        // console.log('User Joined:', user);
        this.onlineDevices.value.push(user);
      })
      .leaving((user: OnlineDevice) => {
        // console.log('User Left:', user);
        this.onlineDevices.value = this.onlineDevices.value.filter(u => u.id !== user.id);
      })
      .listen('SyncUpdateAvailable', (e: any) => {
        // console.log('Sync Update Available:', e);
        this.pull();
      })
      .error((error: any) => {
        // console.error('Echo Error:', error);
        // this.isOnline.value = false; // Don't strictly mark offline, might just be auth/channel error
      });

    // Monitor callback for connection
    window.Echo.connector.pusher.connection.bind('connected', () => {
      this.isOnline.value = true;
      this.pull(); // Pull when reconnected
      this.push();
    });

    window.Echo.connector.pusher.connection.bind('unavailable', () => {
      this.isOnline.value = false;
    });
  }

  public async triggerInitialSync() {
    if (this.isOnline.value) {
      await this.pull();
      await this.push();
    }
  }

  public async push() {
    if (this.isSyncing.value || !this.isOnline.value) return;

    this.isSyncing.value = true;
    this.syncStatus.value = 'syncing';

    try {
      await axios.post('/app/sync/push');
      this.lastSyncTime.value = new Date();
      this.syncStatus.value = 'success';
    } catch (error) {
      console.error('Sync Push Error:', error);
      this.syncStatus.value = 'error';
    } finally {
      this.isSyncing.value = false;
    }
  }

  public async pull() {
    if (this.isSyncing.value || !this.isOnline.value) return;

    this.isSyncing.value = true;
    this.syncStatus.value = 'syncing';

    try {
      await axios.post('/app/sync/pull');
      this.lastSyncTime.value = new Date();
      this.syncStatus.value = 'success';
    } catch (error) {
      // console.error('Sync Pull Error:', error);
      this.syncStatus.value = 'error';
    } finally {
      this.isSyncing.value = false;
    }
  }
}

export const SyncManager = new SyncManagerClient();
