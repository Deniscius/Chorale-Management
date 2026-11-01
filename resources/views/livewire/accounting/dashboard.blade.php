<div>
    <x-page-header title="Synthèse comptable" icon="banknotes"
        :subtitle="'Analyse de la période : '.$this->period['label']">
        <x-slot name="actions">
            <a href="{{ route('transactions.index') }}" wire:navigate class="btn btn-secondary">Opérations</a>
            <a href="{{ route('contributions.index') }}" wire:navigate class="btn btn-secondary">Cotisations</a>
        </x-slot>
    </x-page-header>

    <p class="mb-5 text-sm text-slate-600">La période suit le sélecteur global d'année civile ou pastorale. Les recettes et dépenses proviennent exclusivement des écritures validées ou clôturées.</p>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <x-stat-card label="Recettes validées" :value="number_format($this->figures['income'], 0, ',', ' ').' FCFA'" icon="banknotes" color="green" />
        <x-stat-card label="Dépenses validées" :value="number_format($this->figures['expense'], 0, ',', ' ').' FCFA'" icon="chart" color="red" />
        <x-stat-card label="Solde des opérations" :value="number_format($this->figures['net'], 0, ',', ' ').' FCFA'" icon="banknotes" color="blue" />
        <x-stat-card label="Cotisations clôturées attendues" :value="number_format($this->figures['expected'], 0, ',', ' ').' FCFA'" icon="users" color="slate" />
        <x-stat-card label="Arriérés échus" :value="number_format($this->figures['arrears'], 0, ',', ' ').' FCFA'" icon="alert" color="amber" />
        <x-stat-card label="Cotisations déclarées versées" :value="number_format($this->figures['declared_paid'], 0, ',', ' ').' FCFA'" icon="check" color="green" />
        <x-stat-card label="Dons enregistrés" :value="number_format($this->figures['donation_declared'], 0, ',', ' ').' FCFA'" :hint="$this->figures['donation_count'].' don(s), toutes natures confondues'" icon="banknotes" color="blue" />
        <x-stat-card label="Échéances exonérées" :value="$this->figures['exempt_count']" icon="check" color="slate" />
        <x-stat-card label="Écritures en brouillon" :value="$this->figures['pending']" icon="clock" color="amber" />
    </div>

    <div class="mt-5 rounded-xl border border-slate-200 bg-white p-4 text-sm text-slate-700">
        <strong>Lecture des chiffres :</strong> les dons enregistrés et les cotisations déclarées versées sont présentés à titre de suivi et ne sont pas additionnés aux recettes validées. Le solde correspond aux recettes validées moins les dépenses validées ; il ne représente pas nécessairement le solde bancaire. Les cotisations du mois en cours ne deviennent exigibles qu'après clôture.
    </div>
    <div class="mt-5 flex flex-wrap gap-3">
        <a href="{{ route('contributions.index') }}" wire:navigate class="btn btn-secondary">Analyser les cotisations</a>
        <a href="{{ route('donations.index') }}" wire:navigate class="btn btn-secondary">Analyser les dons</a>
        <a href="{{ route('transactions.index') }}" wire:navigate class="btn btn-secondary">Voir les écritures</a>
    </div>
</div>
