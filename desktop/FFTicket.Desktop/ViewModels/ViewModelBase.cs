using CommunityToolkit.Mvvm.ComponentModel;

namespace FFTicket.Desktop.ViewModels;

public abstract class ViewModelBase : ObservableObject
{
    private bool _isBusy;
    private string _errorMessage = "";
    private string _successMessage = "";

    public bool IsBusy
    {
        get => _isBusy;
        set => SetProperty(ref _isBusy, value);
    }

    public string ErrorMessage
    {
        get => _errorMessage;
        set => SetProperty(ref _errorMessage, value);
    }

    public string SuccessMessage
    {
        get => _successMessage;
        set => SetProperty(ref _successMessage, value);
    }

    protected void ClearMessages()
    {
        ErrorMessage = "";
        SuccessMessage = "";
    }
}

