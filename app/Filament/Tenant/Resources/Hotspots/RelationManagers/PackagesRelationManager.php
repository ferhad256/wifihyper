<?php

namespace App\Filament\Tenant\Resources\Hotspots\RelationManagers;

use App\Models\Package;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Unique;

class PackagesRelationManager extends RelationManager
{
    protected static string $relationship = 'packages';

    protected static ?string $title = 'Packages';

    /**
     * Refuse to operate on a hotspot this tenant does not own.
     *
     * In the normal flow the parent page already 404s on someone else's
     * hotspot, so this never fires. It exists because a Livewire component is
     * addressable in its own right, and packages carry no tenant_id of their
     * own - ownership is only ever transitive through the hotspot, so this is
     * the one place it can be asserted directly.
     */
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord->tenant_id === Auth::guard('tenant')->id();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    // Matches the old controller's explicit duplicate check,
                    // scoped to this hotspot rather than globally.
                    ->unique(
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule): Unique => $rule
                            ->where('hotspot_id', $this->getOwnerRecord()->getKey()),
                    )
                    ->validationMessages([
                        'unique' => 'This hotspot already has a package with that name.',
                    ]),

                TextInput::make('price')
                    ->label('Price (UGX)')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->prefix('UGX'),

                TextInput::make('duration_value')
                    ->label('Duration')
                    ->numeric()
                    ->minValue(0.1)
                    ->step('any'),

                Select::make('duration_unit')
                    ->label('Duration unit')
                    ->options([
                        'minutes' => 'Minutes',
                        'hours' => 'Hours',
                        'days' => 'Days',
                        'weeks' => 'Weeks',
                        'months' => 'Months',
                    ])
                    ->default('hours')
                    ->selectablePlaceholder(false),

                TextInput::make('data_limit_mb')
                    ->label('Data limit (MB)')
                    ->numeric()
                    ->minValue(1)
                    ->helperText('Leave blank for unlimited.'),

                TextInput::make('sort_order')
                    ->label('Display order')
                    ->numeric()
                    ->minValue(0)
                    // The column is NOT NULL; the model mutator also coerces
                    // null to 0, but defaulting here keeps the form honest.
                    ->default(0),

                Toggle::make('is_active')
                    ->label('Offered to customers')
                    ->default(true),

                Textarea::make('description')
                    ->rows(2)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('sort_order')
                    ->label('#')
                    ->alignEnd()
                    ->sortable(),

                TextColumn::make('name')
                    ->searchable()
                    ->weight('bold'),

                TextColumn::make('price')
                    ->money('UGX')
                    ->sortable(),

                TextColumn::make('formatted_duration')
                    ->label('Duration'),

                TextColumn::make('formatted_data_limit')
                    ->label('Data'),

                TextColumn::make('vouchers_count')
                    ->label('Unused stock')
                    ->counts([
                        'vouchers' => fn ($q) => $q->where('status', 'unused'),
                    ])
                    ->alignEnd()
                    ->color(fn ($state): string => $state > 0 ? 'success' : 'danger'),

                IconColumn::make('is_active')
                    ->label('Live')
                    ->boolean(),
            ])
            ->defaultSort('sort_order')
            ->headerActions([
                CreateAction::make()->label('Add package'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No packages yet')
            ->emptyStateDescription('A hotspot needs at least one package before it can sell.');
    }
}
