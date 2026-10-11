import { config } from '@vue/test-utils'
import { i18n } from '../src/i18n'

// Semua mount otomatis dapat plugin i18n — useI18n() di view tidak error.
// Locale default 'id' = teks asli UI → assertion test lama tidak berubah.
config.global.plugins.push(i18n)
