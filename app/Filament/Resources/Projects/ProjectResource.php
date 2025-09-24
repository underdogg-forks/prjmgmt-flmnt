<?php

namespace App\Filament\Resources\Projects;

use App\Exports\ProjectHoursExport;
use App\Filament\Resources\Projects\Pages\CreateProject;
use App\Filament\Resources\Projects\Pages\EditProject;
use App\Filament\Resources\Projects\Pages\ListProjects;
use App\Filament\Resources\Projects\Pages\ViewProject;
use App\Filament\Resources\Projects\RelationManagers\SprintsRelationManager;
use App\Filament\Resources\Projects\RelationManagers\StatusesRelationManager;
use App\Filament\Resources\Projects\RelationManagers\UsersRelationManager;
use App\Models\Project;
use App\Models\ProjectFavorite;
use App\Models\ProjectStatus;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TagsColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Support\Enums\Heroicon;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class ProjectResource extends Resource
{
    protected static ?string $model = Project::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ARCHIVE_BOX_OUTLINE;

    protected static ?int $navigationSort = 1;

    public static function getNavigationLabel(): string
    {
        return trans('proj.projects');
    }

    public static function getPluralLabel(): ?string
    {
        return static::getNavigationLabel();
    }

    public static function getNavigationGroup(): ?string
    {
        return trans('proj.management');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema([
                        Grid::make()
                            ->columns(3)
                            ->schema([
                                SpatieMediaLibraryFileUpload::make('cover')
                                    ->label(trans('proj.cover_image'))
                                    ->image()
                                    ->helperText(
                                        trans('proj.cover_image_helper')
                                    )
                                    ->columnSpan(1),

                                Grid::make()
                                    ->columnSpan(2)
                                    ->schema([
                                        Grid::make()
                                            ->columnSpan(2)
                                            ->columns(12)
                                            ->schema([
                                                TextInput::make('name')
                                                    ->label(trans('proj.project_name'))
                                                    ->required()
                                                    ->columnSpan(10)
                                                    ->maxLength(255),

                                                TextInput::make('ticket_prefix')
                                                    ->label(trans('proj.ticket_prefix'))
                                                    ->maxLength(3)
                                                    ->columnSpan(2)
                                                    ->unique(Project::class, 'ticket_prefix', true)
                                                    ->disabled(
                                                        fn ($record) => $record && $record->tickets()->count() != 0
                                                    )
                                                    ->required(),
                                            ]),

                                        Select::make('owner_id')
                                            ->label(trans('proj.project_owner'))
                                            ->searchable()
                                            ->options(fn () => User::all()->pluck('name', 'id')->toArray())
                                            ->default(fn () => auth()->user()->id)
                                            ->required(),

                                        Select::make('status_id')
                                            ->label(trans('proj.project_status'))
                                            ->searchable()
                                            ->options(fn () => ProjectStatus::all()->pluck('name', 'id')->toArray())
                                            ->default(fn () => ProjectStatus::where('is_default', true)->first()?->id)
                                            ->required(),
                                    ]),

                                RichEditor::make('description')
                                    ->label(trans('proj.project_description'))
                                    ->columnSpan(3),

                                Select::make('type')
                                    ->label(trans('proj.project_type'))
                                    ->searchable()
                                    ->options([
                                        'kanban' => trans('proj.kanban'),
                                        'scrum'  => trans('proj.scrum'),
                                    ])
                                    ->reactive()
                                    ->default(fn () => 'kanban')
                                    ->helperText(function ($state) {
                                        if ($state === 'kanban') {
                                            return trans('proj.kanban_helper');
                                        }
                                        if ($state === 'scrum') {
                                            return trans('proj.scrum_helper');
                                        }

                                        return '';
                                    })
                                    ->required(),

                                Select::make('status_type')
                                    ->label(trans('proj.statuses_configuration'))
                                    ->helperText(
                                        trans('proj.statuses_configuration_helper')
                                    )
                                    ->searchable()
                                    ->options([
                                        'default' => trans('proj.default'),
                                        'custom'  => trans('proj.custom_configuration'),
                                    ])
                                    ->default(fn () => 'default')
                                    ->disabled(fn ($record) => $record && $record->tickets()->count())
                                    ->required(),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('cover')
                    ->label(trans('proj.cover_image'))
                    ->formatStateUsing(fn ($state) => new HtmlString('
                            <div style=\'background-image: url("' . $state . '")\'
                                 class="w-8 h-8 bg-cover bg-center bg-no-repeat"></div>
                        ')),

                TextColumn::make('name')
                    ->label(trans('proj.project_name'))
                    ->sortable()
                    ->searchable(),

                TextColumn::make('owner.name')
                    ->label(trans('proj.project_owner'))
                    ->sortable()
                    ->searchable(),

                TextColumn::make('status.name')
                    ->label(trans('proj.project_status'))
                    ->formatStateUsing(fn ($record) => new HtmlString('
                            <div class="flex items-center gap-2">
                                <span class="filament-tables-color-column relative flex h-6 w-6 rounded-md"
                                    style="background-color: ' . $record->status->color . '"></span>
                                <span>' . $record->status->name . '</span>
                            </div>
                        '))
                    ->sortable()
                    ->searchable(),

                TagsColumn::make('users.name')
                    ->label(trans('proj.affected_users'))
                    ->limit(2),

                TextColumn::make('type')
                    ->bage()
                    ->enum([
                        'kanban' => trans('proj.kanban'),
                        'scrum'  => trans('proj.scrum'),
                    ])
                    ->colors([
                        'secondary' => 'kanban',
                        'warning'   => 'scrum',
                    ]),

                TextColumn::make('created_at')
                    ->label(trans('proj.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->searchable(),
            ])
            ->filters([
                SelectFilter::make('owner_id')
                    ->label(trans('proj.owner'))
                    ->multiple()
                    ->options(fn () => User::all()->pluck('name', 'id')->toArray()),

                SelectFilter::make('status_id')
                    ->label(trans('proj.status'))
                    ->multiple()
                    ->options(fn () => ProjectStatus::all()->pluck('name', 'id')->toArray()),
            ])
            ->recordActions([
                Action::make('favorite')
                    ->label('')
                    ->icon(Heroicon::STAR_OUTLINE)
                    ->color(fn ($record) => auth()->user()->favoriteProjects()
                        ->where('projects.id', $record->id)->count() ? 'success' : 'default')
                    ->action(function ($record) {
                        $projectId       = $record->id;
                        $projectFavorite = ProjectFavorite::where('project_id', $projectId)
                            ->where('user_id', auth()->user()->id)
                            ->first();
                        if ($projectFavorite) {
                            $projectFavorite->delete();
                        } else {
                            ProjectFavorite::create([
                                'project_id' => $projectId,
                                'user_id'    => auth()->user()->id,
                            ]);
                        }
                        Filament::notify('success', trans('proj.project_updated'));
                    }),

                ViewAction::make(),
                EditAction::make(),

                ActionGroup::make([
                    Action::make('exportLogHours')
                        ->label(trans('proj.export_hours'))
                        ->icon(Heroicon::DOCUMENT_ARROW_DOWN_OUTLINE)
                        ->color('gray')
                        ->action(fn ($record) => Excel::download(
                            new ProjectHoursExport($record),
                            'time_' . Str::slug($record->name) . '.csv',
                            \Maatwebsite\Excel\Excel::CSV,
                            ['Content-Type' => 'text/csv']
                        )),

                    Action::make('kanban')
                        ->label(
                            fn ($record) => ($record->type === 'scrum' ? trans('proj.scrum_board') : trans('proj.kanban_board'))
                        )
                        ->icon(Heroicon::VIEW_COLUMNS_OUTLINE)
                        ->color('gray')
                        ->url(function ($record) {
                            if ($record->type === 'scrum') {
                                return route('filament.pages.scrum/{project}', ['project' => $record->id]);
                            }

                            return route('filament.pages.kanban/{project}', ['project' => $record->id]);
                        }),
                ])->color('gray'),
            ])
            ->groupedBulkActions([
                DeleteBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            SprintsRelationManager::class,
            UsersRelationManager::class,
            StatusesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListProjects::route('/'),
            'create' => CreateProject::route('/create'),
            'view'   => ViewProject::route('/{record}'),
            'edit'   => EditProject::route('/{record}/edit'),
        ];
    }
}
