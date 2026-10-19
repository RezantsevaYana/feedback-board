import { defineCustomElement } from 'vue'
import WidgetApp from './WidgetApp.vue'
import styles from '@/scss/widget.scss?inline'

// <script src="https://<service>/widget.js" data-project="demo" [data-position="bottom-left"] defer></script>
// The widget mounts a <feedback-board-widget> element; Vue renders it into Shadow DOM, so the host
// site's CSS and the widget's CSS do not affect each other (TZ 5.1).
const TAG = 'feedback-board-widget'
const script = document.currentScript as HTMLScriptElement | null
const project = script?.dataset.project

if (!project) {
  console.warn('[feedback-board] widget.js: add data-project="<slug>" to the script tag')
} else if (!customElements.get(TAG)) {
  // styles go into the shadow root, so the widget looks the same on any site
  customElements.define(TAG, defineCustomElement(WidgetApp, { styles: [styles] }))
  const el = document.createElement(TAG)
  el.setAttribute('project', project)
  // API and board live where widget.js is served from
  el.setAttribute('origin', new URL(script.src).origin)
  el.setAttribute('position', script.dataset.position === 'bottom-left' ? 'bottom-left' : 'bottom-right')
  document.body.appendChild(el)
}