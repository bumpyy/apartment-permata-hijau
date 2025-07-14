# Court Booking Component Structure

This directory contains the refactored Livewire court booking component, organized following industry standards for better maintainability and separation of concerns.

## 📁 Directory Structure

```
court-booking/
├── main.blade.php          # Main Livewire component (PHP class + template)
├── ui/                     # Reusable UI components
│   ├── header.blade.php           # Page header with court info and booking status
│   ├── navigation.blade.php       # View mode switcher and navigation controls
│   ├── booking-rules.blade.php    # Booking rules display
│   ├── login-prompt.blade.php     # Login reminder for non-authenticated users
│   ├── quota-display.blade.php    # User quota information display
│   ├── quota-warning.blade.php    # Quota violation warnings
│   ├── real-time-status.blade.php # Real-time status indicators
│   ├── selection-summary.blade.php # Selected slots summary
│   ├── legend.blade.php           # Booking type legend
│   └── confirm-button.blade.php   # Booking confirmation button
├── views/                  # Main view templates
│   ├── weekly-view.blade.php      # Weekly calendar view
│   ├── monthly-view.blade.php     # Monthly calendar view
│   └── daily-view.blade.php       # Daily time slot view
├── modals/                 # Modal dialogs
│   ├── time-selector.blade.php    # Time slot selection modal
│   ├── confirmation.blade.php     # Booking confirmation modal
│   ├── thank-you.blade.php        # Success confirmation modal
│   └── login-reminder.blade.php   # Login reminder modal
└── README.md              # This documentation
```

## 🔧 Architecture

### Main Component (`main.blade.php`)
- Contains the Livewire PHP class with all business logic
- Handles data loading, validation, and state management
- Uses `@include` statements to include UI components and views
- No props needed - variables are shared through parent scope

### UI Components (`ui/`)
- Reusable UI elements that can be included anywhere
- No props required - access variables directly from parent scope
- Focused on presentation and user interaction
- Self-contained and maintainable

### Views (`views/`)
- Main view templates for different calendar modes
- Handle the specific layout and logic for each view type
- Access parent component variables directly

### Modals (`modals/`)
- Modal dialogs for user interactions
- Self-contained with their own conditional rendering
- Access parent component variables directly

## 🚀 Benefits of This Structure

1. **Separation of Concerns**: Each file has a single responsibility
2. **Maintainability**: Easier to find and modify specific functionality
3. **Reusability**: UI components can be reused in other parts of the application
4. **Readability**: Smaller, focused files are easier to understand
5. **Industry Standards**: Follows common Laravel/Livewire patterns
6. **No Props Overhead**: Direct variable access eliminates prop passing complexity

## 📝 Usage

The main component automatically includes all necessary UI elements and views based on the current state. No additional configuration is needed - just include the main component in your routes or other views.

## 🔄 Migration Notes

- All components now use `@include` instead of component syntax
- No props are passed - variables are accessed directly from parent scope
- Folder structure follows semantic naming conventions
- All functionality remains the same, just better organized
