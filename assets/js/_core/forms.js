// to enable the enqueue of this optional JS file, 
// you'll have to uncomment a row in the functions.php file
// just read the comments in there mate


document.addEventListener('DOMContentLoaded', function() {
    initializeFormSubmission();
});

function handleFormSubmission(event) {
    event.preventDefault();
    const form = event.target;
    const formId = form.getAttribute('data-form-id');
    const redirectUrl = form.getAttribute('data-redirect');
    const actionUrl = `/wp-json/contact-form-7/v1/contact-forms/${formId}/feedback`;
    const formData = new FormData(form);
    formData.append('_wpcf7_unit_tag', formId);

    console.log('Form Submitting...')
    console.log('id:', formId, 'redirect:', redirectUrl, 'action:', actionUrl, 'data:', formData);
    fetch(actionUrl, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'mail_sent') {
           
            if (redirectUrl) {
                // navigate to redirect url with swup 
                swup.navigate(redirectUrl);
            }
        } else {
            alert('Form submission failed');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while submitting the form');
    });
}

function initializeFormSubmission() {
    const forms = document.querySelectorAll('form[data-form-id]');
    forms.forEach(form => {
        form.addEventListener('submit', handleFormSubmission);
    });
}

// Call the function to initialize form submission
initializeFormSubmission();