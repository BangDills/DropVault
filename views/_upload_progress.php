<?php /* Upload progress partial — included by dashboard views */ ?>
      <div x-show="uploads.length" x-cloak class="space-y-2 mb-5">
        <template x-for="u in uploads" :key="u.id">
          <div class="cv-card px-4 py-3 flex items-center gap-3">
            <div class="flex-1">
              <div class="flex justify-between text-xs mb-1.5">
                <span x-text="u.name" class="truncate font-medium"></span>
                <span x-text="u.status" class="text-cv-muted"></span>
              </div>
              <div class="h-1.5 bg-cv-bg rounded-full overflow-hidden">
                <div class="h-full bg-cv-accent rounded-full transition-all" :style="`width:${u.pct}%`"></div>
              </div>
            </div>
          </div>
        </template>
      </div>
