import './firebase';
import './echo';

// O Livewire 3 traz o próprio Alpine — com focus, intersect, collapse e os
// demais plugins oficiais. Importar e iniciar um segundo Alpine faria as duas
// instâncias inicializarem o mesmo DOM: cada clique alternaria um menu duas
// vezes. Por isso o Alpine vem do bundle ESM do Livewire, e os layouts usam
// @livewireScriptConfig em vez de @livewireScripts.
import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm';

import registerShell from './shell';

registerShell(Alpine);

Livewire.start();
